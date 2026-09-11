<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\SellerProfiles\Pages\ListSellerProfiles;
use App\Filament\Resources\SellerProfiles\Pages\ViewSellerProfile;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Models\Product;
use App\Models\Report;
use App\Models\SellerProfile;
use App\Models\User;
use App\Notifications\SellerVerificationNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
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
        $seller = SellerProfile::factory()->create(['nida_number' => str_repeat('1', 20)]);

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

    /**
     * B4 (tester feedback): "When an admin approves a shop, the seller
     * learns nothing." Verifies both halves the report calls for — an
     * in-app notification (a real `app_notifications` row, not just the
     * admin's own Filament toast) and an email when the seller has an
     * address.
     */
    public function test_verifying_a_seller_notifies_them_in_app_and_by_email(): void
    {
        Notification::fake();
        $admin = User::factory()->admin()->create();
        $sellerUser = User::factory()->create(['email' => 'seller@example.com']);
        $seller = SellerProfile::factory()->create(['user_id' => $sellerUser->id, 'shop_name' => 'Amina Electronics']);

        $this->actingAsAdmin($admin);
        Livewire::test(ViewSellerProfile::class, ['record' => $seller->id])->callAction('verify');

        $this->assertDatabaseHas('app_notifications', ['user_id' => $sellerUser->id]);
        Notification::assertSentTo($sellerUser, SellerVerificationNotification::class);
    }

    public function test_verifying_a_seller_with_no_email_still_notifies_in_app_only(): void
    {
        Notification::fake();
        $admin = User::factory()->admin()->create();
        $sellerUser = User::factory()->create(['email' => null]);
        $seller = SellerProfile::factory()->create(['user_id' => $sellerUser->id]);

        $this->actingAsAdmin($admin);
        Livewire::test(ViewSellerProfile::class, ['record' => $seller->id])->callAction('verify');

        $this->assertDatabaseHas('app_notifications', ['user_id' => $sellerUser->id]);
        Notification::assertNotSentTo($sellerUser, SellerVerificationNotification::class);
    }

    public function test_rejecting_a_seller_notifies_them_in_app_and_by_email_with_the_reason(): void
    {
        Notification::fake();
        $admin = User::factory()->admin()->create();
        $sellerUser = User::factory()->create(['email' => 'seller@example.com']);
        $seller = SellerProfile::factory()->create(['user_id' => $sellerUser->id]);

        $this->actingAsAdmin($admin);
        Livewire::test(ViewSellerProfile::class, ['record' => $seller->id])
            ->callAction('reject', data: ['reason' => 'NIDA number could not be verified']);

        $this->assertDatabaseHas('app_notifications', ['user_id' => $sellerUser->id]);
        Notification::assertSentTo(
            $sellerUser,
            SellerVerificationNotification::class,
            fn ($notification) => str_contains($notification->toMail($sellerUser)->introLines[1], 'NIDA number could not be verified'),
        );
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

    /**
     * D1 (tester feedback): "edit ... role/status." `is_admin`/`role` are
     * deliberately outside User's #[Fillable] (same reason banned_at/
     * ban_reason are forceFill-only) — a version of this form/page once
     * relied on plain mass-assignment, which silently dropped both on
     * every save (no exception; preventSilentlyDiscardingAttributes()
     * isn't enabled anywhere in this app). This proves the real save
     * path, not just that the form renders.
     */
    public function test_editing_a_user_persists_is_admin_and_role(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->create(['is_admin' => false, 'role' => null]);

        $this->actingAsAdmin($admin);

        Livewire::test(EditUser::class, ['record' => $target->id])
            ->fillForm(['is_admin' => true, 'role' => 'staff'])
            ->call('save')
            ->assertHasNoFormErrors();

        $target->refresh();
        $this->assertTrue($target->is_admin);
        $this->assertSame('staff', $target->role);
        $this->assertDatabaseHas('activity_logs', ['action' => 'user.updated', 'subject_id' => $target->id]);
    }

    /** Demoting an admin back to a plain user must not leave a stale role behind. */
    public function test_removing_admin_access_clears_the_role(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->admin()->create();

        $this->actingAsAdmin($admin);

        Livewire::test(EditUser::class, ['record' => $target->id])
            ->fillForm(['is_admin' => false])
            ->call('save')
            ->assertHasNoFormErrors();

        $target->refresh();
        $this->assertFalse($target->is_admin);
        $this->assertNull($target->role);
    }

    /**
     * D1: email is nullable in the schema and plenty of real accounts —
     * phone-OTP buyers/sellers — have none. A prior version of this form
     * marked email `required()` unconditionally, which made it impossible
     * to save ANY edit (even just fixing a typo'd name) on such an
     * account without inventing a fake email first.
     */
    public function test_editing_a_phone_only_user_does_not_require_an_email(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->create(['email' => null, 'phone' => '+255754000111']);

        $this->actingAsAdmin($admin);

        Livewire::test(EditUser::class, ['record' => $target->id])
            ->fillForm(['name' => 'Corrected Name'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Corrected Name', $target->fresh()->name);
    }

    public function test_creating_a_staff_account_persists_is_admin_and_role(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAsAdmin($admin);

        Livewire::test(CreateUser::class)
            ->fillForm([
                'name' => 'New Staffer',
                'email' => 'staffer@example.com',
                'password' => 'password123',
                'is_admin' => true,
                'role' => 'staff',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $created = User::where('email', 'staffer@example.com')->firstOrFail();
        $this->assertTrue($created->is_admin);
        $this->assertSame('staff', $created->role);
        $this->assertDatabaseHas('activity_logs', ['action' => 'user.created', 'subject_id' => $created->id]);
    }

    /**
     * D1: "never allow deleting own account or another admin." This used
     * to be enforced only inside UsersTable's own delete-action closure —
     * the policy itself ignored the target record entirely, so Filament's
     * generic DeleteAction (as EditUser's header used to expose it,
     * unprotected) would have authorized deleting yourself or a fellow
     * admin. The policy is the actual authorization boundary Filament
     * checks everywhere; this proves it holds regardless of which UI
     * action asks.
     */
    public function test_the_user_policy_refuses_deleting_your_own_account_or_another_admin(): void
    {
        $admin = User::factory()->admin()->create();
        $otherAdmin = User::factory()->admin()->create();
        $ordinaryUser = User::factory()->create();

        $this->actingAsAdmin($admin);

        $this->assertFalse(Gate::allows('delete', $admin));
        $this->assertFalse(Gate::allows('delete', $otherAdmin));
        $this->assertTrue(Gate::allows('delete', $ordinaryUser));
    }
}
