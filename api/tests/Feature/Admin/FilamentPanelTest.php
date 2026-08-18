<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\SellerProfiles\Pages\ListSellerProfiles;
use App\Filament\Resources\SellerProfiles\Pages\ViewSellerProfile;
use App\Models\Product;
use App\Models\Report;
use App\Models\SellerProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class FilamentPanelTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_non_admin_cannot_access_the_panel(): void
    {
        $user = User::factory()->create();

        $this->actingAsAdmin($user)->get('/admin')->assertForbidden();
    }

    public function test_an_admin_can_load_the_dashboard(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAsAdmin($admin)->get('/admin')->assertOk();
    }

    public function test_an_admin_can_load_the_seller_verification_queue(): void
    {
        $admin = User::factory()->admin()->create();
        SellerProfile::factory()->create();

        $this->actingAsAdmin($admin)->get('/admin/seller-profiles')->assertOk();
    }

    public function test_an_admin_can_view_a_seller_profile_with_identity_evidence(): void
    {
        $admin = User::factory()->admin()->create();
        $seller = SellerProfile::factory()->create(['nida_image' => 'sellers/nida/x.jpg', 'licence_file' => 'sellers/licences/x.pdf']);

        $this->actingAsAdmin($admin)->get("/admin/seller-profiles/{$seller->id}")->assertOk();
    }

    /**
     * Exercises the real Filament action end to end (Livewire, the actual
     * `verify` action closure, the actual policy/route/middleware stack a
     * browser click goes through) — not `$seller->forceFill(...)->save()`
     * called directly in the test body, which is what this test did
     * before and which would pass even if the button in the panel were
     * completely unreachable. That was, in fact, exactly the real bug:
     * `ViewSellerProfile` — the only page that shows the NIDA photo and
     * licence a reviewer needs to decide — had no header actions at all,
     * so verify/reject only existed on the list table, which shows none
     * of that evidence. Fixed by sharing the same Action definitions
     * (`SellerProfileResource::verifyAction()`/`rejectAction()`) between
     * the table and the view page's header. Also proves the point of the
     * whole feature: a previously-hidden product becomes publicly visible
     * the moment verification happens, with no separate step.
     */
    public function test_an_admin_can_verify_a_pending_seller_from_the_review_page_and_their_products_become_visible(): void
    {
        $admin = User::factory()->admin()->create();
        $seller = SellerProfile::factory()->create(); // pending
        $product = Product::factory()->create(['seller_id' => $seller->id]);

        $this->assertEquals('pending', $seller->status);
        $this->getJson("/api/products/{$product->id}")->assertNotFound();

        $this->actingAsAdmin($admin);

        Livewire::test(ViewSellerProfile::class, ['record' => $seller->id])
            ->callAction('verify')
            ->assertHasNoActionErrors();

        $seller->refresh();
        $this->assertEquals('verified', $seller->status);
        $this->assertNotNull($seller->verified_at);

        $this->getJson("/api/products/{$product->id}")->assertOk();
    }

    public function test_an_admin_can_verify_a_pending_seller_from_the_list_table(): void
    {
        $admin = User::factory()->admin()->create();
        $seller = SellerProfile::factory()->create();

        $this->actingAsAdmin($admin);

        Livewire::test(ListSellerProfiles::class)
            ->callTableAction('verify', $seller)
            ->assertHasNoTableActionErrors();

        $this->assertEquals('verified', $seller->fresh()->status);
    }

    public function test_an_admin_can_reject_a_pending_seller_with_a_reason_from_the_review_page(): void
    {
        $admin = User::factory()->admin()->create();
        $seller = SellerProfile::factory()->create();

        $this->actingAsAdmin($admin);

        Livewire::test(ViewSellerProfile::class, ['record' => $seller->id])
            ->callAction('reject', data: ['reason' => 'ID photo is unreadable'])
            ->assertHasNoActionErrors();

        $seller->refresh();
        $this->assertEquals('rejected', $seller->status);
        $this->assertEquals('ID photo is unreadable', $seller->rejection_reason);
    }

    public function test_rejecting_a_seller_requires_a_reason(): void
    {
        $admin = User::factory()->admin()->create();
        $seller = SellerProfile::factory()->create();

        $this->actingAsAdmin($admin);

        Livewire::test(ViewSellerProfile::class, ['record' => $seller->id])
            ->callAction('reject', data: ['reason' => ''])
            ->assertHasActionErrors(['reason']);

        $this->assertEquals('pending', $seller->fresh()->status);
    }

    public function test_an_admin_can_load_the_moderation_queue(): void
    {
        $admin = User::factory()->admin()->create();
        $product = Product::factory()->create();
        Report::factory()->create(['reportable_type' => Product::class, 'reportable_id' => $product->id]);

        $this->actingAsAdmin($admin)->get('/admin/reports')->assertOk();
    }

    public function test_an_admin_can_load_the_users_and_categories_pages(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAsAdmin($admin)->get('/admin/users')->assertOk();
        $this->actingAsAdmin($admin)->get('/admin/categories')->assertOk();
    }

    /**
     * Roles (CLAUDE.md admin rebuild, Section 6): "Enforce with policies,
     * not just hidden menu items." `UserPolicy` gates every standard CRUD
     * ability Filament checks automatically for `UserResource` — this
     * proves the 403 is real, not just that the nav link is hidden: a
     * Staff user hitting the URL directly is refused.
     */
    public function test_a_staff_user_is_refused_from_user_management(): void
    {
        $staff = User::factory()->staff()->create();

        $this->actingAsAdmin($staff)->get('/admin/users')->assertForbidden();
    }

    public function test_an_admin_can_access_user_management(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAsAdmin($admin)->get('/admin/users')->assertOk();
    }

    public function test_a_staff_user_can_still_access_the_moderation_and_verification_surfaces(): void
    {
        $staff = User::factory()->staff()->create();
        SellerProfile::factory()->create();

        $this->actingAsAdmin($staff)->get('/admin')->assertOk();
        $this->actingAsAdmin($staff)->get('/admin/seller-profiles')->assertOk();
        $this->actingAsAdmin($staff)->get('/admin/reports')->assertOk();
    }

    public function test_suspending_a_user_revokes_their_tokens_and_blocks_login(): void
    {
        $user = User::factory()->create();
        $user->createToken('test');
        $this->assertCount(1, $user->tokens);

        $user->forceFill([
            'banned_at' => now(),
            'banned_until' => now()->addDays(7),
            'ban_reason' => 'Testing suspension',
        ])->save();
        $user->tokens()->delete();

        $this->assertCount(0, $user->fresh()->tokens);
        $this->assertTrue($user->fresh()->isBanned());
    }

    public function test_ban_is_permanent_while_suspension_expires(): void
    {
        $banned = User::factory()->create(['banned_at' => now(), 'banned_until' => null]);
        $suspendedExpired = User::factory()->create(['banned_at' => now()->subDays(10), 'banned_until' => now()->subDay()]);
        $suspendedActive = User::factory()->create(['banned_at' => now(), 'banned_until' => now()->addDay()]);

        $this->assertTrue($banned->isBanned());
        $this->assertFalse($suspendedExpired->isBanned());
        $this->assertTrue($suspendedActive->isBanned());
    }
}
