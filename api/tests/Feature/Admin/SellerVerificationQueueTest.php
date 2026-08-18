<?php

namespace Tests\Feature\Admin;

use App\Filament\Pages\SellerVerificationQueue;
use App\Models\Product;
use App\Models\SellerProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    /** SellerProfileResource stays reachable for deep links but is off the main nav — the queue page owns that slot now. */
    public function test_the_seller_profile_resource_is_hidden_from_navigation_but_still_reachable(): void
    {
        $admin = User::factory()->admin()->create();
        $seller = SellerProfile::factory()->verified()->create();

        $this->assertFalse(\App\Filament\Resources\SellerProfiles\SellerProfileResource::shouldRegisterNavigation());
        $this->actingAsAdmin($admin)->get("/admin/seller-profiles/{$seller->id}")->assertOk();
    }
}
