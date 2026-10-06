<?php

namespace Tests\Feature\Auth;

use App\Models\Device;
use App\Models\Product;
use App\Models\SellerProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

/**
 * Apple App Review rejection, PART C: Guideline 5.1.1(v) — account
 * creation happens in-app, so deletion must too. `DELETE /api/auth/me`
 * (AccountDeletionService) is the in-app path; this suite proves it
 * actually makes the account gone, not just flagged, while preserving
 * what must legally/structurally be preserved (orders).
 */
class AccountDeletionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    public function test_deleting_the_account_requires_authentication(): void
    {
        $this->deleteJson('/api/auth/me')->assertStatus(401);
    }

    public function test_a_plain_buyer_account_is_deleted_and_logged_out_everywhere(): void
    {
        $user = User::factory()->create(['name' => 'Amina Buyer', 'phone' => '+255754123456']);
        $tokenA = $user->createToken('device-a')->plainTextToken;
        $tokenB = $user->createToken('device-b')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$tokenA}")
            ->deleteJson('/api/auth/me')
            ->assertOk();

        $this->assertSoftDeleted('users', ['id' => $user->id]);
        $this->assertDatabaseCount('personal_access_tokens', 0);

        // Both the token used to delete and every other device's token are
        // gone at the source Sanctum itself checks (`PersonalAccessToken::
        // findToken()`) — not re-asserted via a second simulated HTTP
        // request, since the test HTTP kernel caches the resolved guard
        // user across calls within one test method regardless of which
        // token a later call presents.
        $this->assertNull(PersonalAccessToken::findToken(explode('|', $tokenA, 2)[1]));
        $this->assertNull(PersonalAccessToken::findToken(explode('|', $tokenB, 2)[1]));
    }

    public function test_personally_identifying_fields_are_overwritten_not_merely_flagged(): void
    {
        $user = User::factory()->create([
            'name' => 'Amina Buyer',
            'phone' => '+255754123456',
            'email' => 'amina@example.com',
            'avatar' => 'https://example.com/avatars/amina.jpg',
        ]);
        $token = $user->createToken('device')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")->deleteJson('/api/auth/me')->assertOk();

        $fresh = User::withTrashed()->find($user->id);
        $this->assertSame('Deleted user', $fresh->name);
        $this->assertNull($fresh->email);
        $this->assertNull($fresh->avatar);
        $this->assertNotSame('+255754123456', $fresh->phone, 'the real phone number must not remain readable');
    }

    public function test_the_freed_phone_number_can_be_used_to_register_a_fresh_account(): void
    {
        $user = User::factory()->create(['phone' => '+255754123456']);
        $token = $user->createToken('device')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")->deleteJson('/api/auth/me')->assertOk();

        $this->assertFalse(User::query()->where('phone', '+255754123456')->exists());
    }

    public function test_the_sellers_shop_and_products_are_removed_from_public_view(): void
    {
        $user = User::factory()->create();
        $shop = SellerProfile::factory()->for($user)->create(['status' => 'verified']);
        $product = Product::factory()->for($shop, 'seller')->create(['is_active' => true]);
        $token = $user->createToken('device')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")->deleteJson('/api/auth/me')->assertOk();

        $this->assertSoftDeleted('seller_profiles', ['id' => $shop->id]);
        $this->assertSoftDeleted('products', ['id' => $product->id]);
    }

    public function test_registered_push_devices_are_forgotten(): void
    {
        $user = User::factory()->create();
        Device::factory()->for($user)->create();
        $token = $user->createToken('device')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")->deleteJson('/api/auth/me')->assertOk();

        $this->assertDatabaseCount('devices', 0);
    }

    public function test_the_avatar_file_itself_is_deleted_from_storage(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post('/api/auth/avatar', [
            'avatar' => \Illuminate\Http\UploadedFile::fake()->image('me.jpg'),
        ])->assertOk();
        $path = str($user->fresh()->avatar)->after(Storage::disk('public')->url(''))->toString();
        Storage::disk('public')->assertExists($path);
        $token = $user->fresh()->createToken('device')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")->deleteJson('/api/auth/me')->assertOk();

        Storage::disk('public')->assertMissing($path);
    }

    public function test_orders_are_preserved_for_record_keeping_not_deleted(): void
    {
        $buyer = User::factory()->create();
        $seller = User::factory()->create();
        $shop = SellerProfile::factory()->for($seller)->create();
        $order = \App\Models\Order::factory()->create(['buyer_id' => $buyer->id, 'seller_id' => $shop->id]);
        $token = $buyer->createToken('device')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")->deleteJson('/api/auth/me')->assertOk();

        // Orders have no soft-delete column at all — they're never touched,
        // not even flagged, by account deletion. The buyer's own name on
        // this order now reads as "Deleted user", the intended outcome.
        $this->assertDatabaseHas('orders', ['id' => $order->id]);
        $this->assertSame('Deleted user', $buyer->fresh()->name);
    }
}
