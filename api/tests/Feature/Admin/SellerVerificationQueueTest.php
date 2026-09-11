<?php

namespace Tests\Feature\Admin;

use App\Filament\Pages\SellerVerificationQueue;
use App\Models\Product;
use App\Models\SellerProfile;
use App\Models\User;
use App\Notifications\SellerVerificationNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class SellerVerificationQueueTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_admin_can_load_the_queue(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAsAdmin($admin)->get('/admin/seller-verification')->assertOk();
    }

    public function test_a_staff_user_can_load_the_queue(): void
    {
        $staff = User::factory()->staff()->create();

        $this->actingAsAdmin($staff)->get('/admin/seller-verification')->assertOk();
    }

    public function test_the_queue_shows_only_pending_sellers_oldest_first(): void
    {
        $admin = User::factory()->admin()->create();
        $newer = SellerProfile::factory()->create(['created_at' => now()->subHour()]);
        $older = SellerProfile::factory()->create(['created_at' => now()->subDays(3)]);
        SellerProfile::factory()->verified()->create();

        $this->actingAsAdmin($admin);

        $pending = Livewire::test(SellerVerificationQueue::class)->instance()->getPending();

        $this->assertCount(2, $pending);
        $this->assertSame($older->id, $pending->first()->id);
        $this->assertSame($newer->id, $pending->last()->id);
    }

    public function test_approving_from_the_queue_verifies_the_seller_and_reveals_their_products(): void
    {
        $admin = User::factory()->admin()->create();
        $seller = SellerProfile::factory()->create();
        $product = Product::factory()->create(['seller_id' => $seller->id]);

        $this->getJson("/api/products/{$product->id}")->assertNotFound();

        $this->actingAsAdmin($admin);

        Livewire::test(SellerVerificationQueue::class)->call('approve', $seller->id);

        $seller->refresh();
        $this->assertSame('verified', $seller->status);
        $this->getJson("/api/products/{$product->id}")->assertOk();
    }

    public function test_rejecting_from_the_queue_requires_a_reason(): void
    {
        $admin = User::factory()->admin()->create();
        $seller = SellerProfile::factory()->create();

        $this->actingAsAdmin($admin);

        Livewire::test(SellerVerificationQueue::class)
            ->set("rejectReasons.{$seller->id}", '')
            ->call('reject', $seller->id);

        $this->assertSame('pending', $seller->fresh()->status);

        Livewire::test(SellerVerificationQueue::class)
            ->set("rejectReasons.{$seller->id}", 'Blurry NIDA photo')
            ->call('reject', $seller->id);

        $seller->refresh();
        $this->assertSame('rejected', $seller->status);
        $this->assertSame('Blurry NIDA photo', $seller->rejection_reason);
    }

    /**
     * D2 (tester feedback): approve/reject here used to forceFill+save
     * with no push/email to the seller at all — the B4 notification
     * pairing only ever landed in SellerProfileResource's own actions,
     * never this page, even though this is the one every reviewer
     * actually uses day to day (SellerProfileResource is off the main
     * nav — see the test below). Extracted into SellerVerificationService
     * so both call sites share one implementation.
     */
    public function test_approving_from_the_queue_notifies_the_seller_in_app_and_by_email(): void
    {
        Notification::fake();
        $admin = User::factory()->admin()->create();
        $sellerUser = User::factory()->create(['email' => 'seller@example.com']);
        $seller = SellerProfile::factory()->create(['user_id' => $sellerUser->id, 'shop_name' => 'Amina Electronics']);

        $this->actingAsAdmin($admin);
        Livewire::test(SellerVerificationQueue::class)->call('approve', $seller->id);

        $this->assertDatabaseHas('app_notifications', ['user_id' => $sellerUser->id]);
        Notification::assertSentTo($sellerUser, SellerVerificationNotification::class);
    }

    public function test_rejecting_from_the_queue_notifies_the_seller_in_app_and_by_email_with_the_reason(): void
    {
        Notification::fake();
        $admin = User::factory()->admin()->create();
        $sellerUser = User::factory()->create(['email' => 'seller@example.com']);
        $seller = SellerProfile::factory()->create(['user_id' => $sellerUser->id]);

        $this->actingAsAdmin($admin);
        Livewire::test(SellerVerificationQueue::class)
            ->set("rejectReasons.{$seller->id}", 'NIDA number could not be verified')
            ->call('reject', $seller->id);

        $this->assertDatabaseHas('app_notifications', ['user_id' => $sellerUser->id]);
        Notification::assertSentTo(
            $sellerUser,
            SellerVerificationNotification::class,
            fn ($notification) => str_contains($notification->toMail($sellerUser)->introLines[1], 'NIDA number could not be verified'),
        );
    }

    /**
     * D2: "NIDA number as large copyable text" — a plain small line of
     * text before this. Pins the large/mono styling and the copy
     * affordance in place rather than the number merely appearing
     * somewhere on the page.
     */
    public function test_the_nida_number_renders_as_large_copyable_text(): void
    {
        $admin = User::factory()->admin()->create();
        SellerProfile::factory()->create(['nida_number' => '19900101123456789012']);

        $response = $this->actingAsAdmin($admin)->get('/admin/seller-verification');

        $response->assertOk();
        $response->assertSee('19900101123456789012');
        $response->assertSee('text-2xl', false);
        $response->assertSee('clipboard.writeText', false);
    }

    /**
     * D2: "Approve/Reject prominent at top, not buried" — these used to
     * sit in a footer bar below both columns of business/NIDA content.
     * Confirmed by position, not just presence: the Verify button's
     * markup must appear before the two-column content grid's own marker
     * in the raw HTML.
     */
    public function test_verify_and_reject_appear_above_the_seller_details_grid(): void
    {
        $admin = User::factory()->admin()->create();
        SellerProfile::factory()->create();

        $html = $this->actingAsAdmin($admin)->get('/admin/seller-verification')->getContent();

        // The button's own text sits on its own line inside the component
        // markup (">\n    Verify\n<"), not tight against the angle
        // brackets — matched loosely rather than assuming exact adjacency.
        preg_match('/>\s*Verify\s*</', $html, $matches, PREG_OFFSET_CAPTURE);
        $verifyPosition = $matches[0][1] ?? false;
        $gridPosition = strpos($html, 'Business details');

        $this->assertNotFalse($verifyPosition);
        $this->assertNotFalse($gridPosition);
        $this->assertLessThan($gridPosition, $verifyPosition);
    }

    /** SellerProfileResource stays reachable for deep links but is off the main nav — the queue page owns that slot now. */
    public function test_the_seller_profile_resource_is_hidden_from_navigation_but_still_reachable(): void
    {
        $admin = User::factory()->admin()->create();
        $seller = SellerProfile::factory()->verified()->create();

        $this->assertFalse(\App\Filament\Resources\SellerProfiles\SellerProfileResource::shouldRegisterNavigation());
        $this->actingAsAdmin($admin)->get("/admin/seller-profiles/{$seller->id}")->assertOk();
    }
}
