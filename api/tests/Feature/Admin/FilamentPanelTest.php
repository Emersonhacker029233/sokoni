<?php

namespace Tests\Feature\Admin;

use App\Models\Product;
use App\Models\Report;
use App\Models\SellerProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    public function test_an_admin_can_verify_a_pending_seller(): void
    {
        $admin = User::factory()->admin()->create();
        $seller = SellerProfile::factory()->create(); // pending

        $this->assertEquals('pending', $seller->status);

        $seller->forceFill(['status' => 'verified', 'verified_at' => now()])->save();

        $this->assertEquals('verified', $seller->fresh()->status);
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
