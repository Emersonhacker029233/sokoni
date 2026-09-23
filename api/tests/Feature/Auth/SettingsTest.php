<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Part 4 (client feedback): the Settings screen's server-side surface — profile photo, notification toggles, phone number change. */
class SettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    public function test_user_can_upload_a_profile_photo(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->post('/api/auth/avatar', ['avatar' => UploadedFile::fake()->image('me.jpg', 800, 800)])
            ->assertOk();

        $this->assertNotNull($response->json('data.avatar'));
        $this->assertDatabaseHas('users', ['id' => $user->id]);
        $this->assertNotNull($user->fresh()->avatar);
    }

    public function test_uploading_a_new_photo_deletes_the_previous_one(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/api/auth/avatar', ['avatar' => UploadedFile::fake()->image('first.jpg')])
            ->assertOk();
        $firstPath = str($user->fresh()->avatar)->after(Storage::disk('public')->url(''))->toString();
        Storage::disk('public')->assertExists($firstPath);

        $this->actingAs($user)
            ->post('/api/auth/avatar', ['avatar' => UploadedFile::fake()->image('second.jpg')])
            ->assertOk();

        Storage::disk('public')->assertMissing($firstPath);
    }

    public function test_user_can_remove_their_profile_photo(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)
            ->post('/api/auth/avatar', ['avatar' => UploadedFile::fake()->image('me.jpg')])
            ->assertOk();

        $response = $this->actingAs($user)->deleteJson('/api/auth/avatar')->assertOk();

        $this->assertNull($response->json('data.avatar'));
        $this->assertNull($user->fresh()->avatar);
    }

    public function test_avatar_upload_requires_authentication(): void
    {
        $this->post('/api/auth/avatar', ['avatar' => UploadedFile::fake()->image('me.jpg')])
            ->assertStatus(401);
    }

    public function test_avatar_upload_rejects_a_non_image_file(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/api/auth/avatar', ['avatar' => UploadedFile::fake()->create('resume.pdf', 500)])
            ->assertStatus(422);
    }

    public function test_user_can_update_a_single_notification_toggle_without_affecting_the_others(): void
    {
        $user = User::factory()->create([
            'notify_orders' => true,
            'notify_messages' => true,
            'notify_offers' => true,
            'marketing_consent' => false,
        ]);

        $this->actingAs($user)
            ->patchJson('/api/auth/notification-preferences', ['notify_messages' => false])
            ->assertOk()
            ->assertJsonPath('data.notify_messages', false)
            ->assertJsonPath('data.notify_orders', true)
            ->assertJsonPath('data.notify_offers', true)
            ->assertJsonPath('data.notify_marketing', false);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'notify_orders' => true,
            'notify_messages' => false,
            'notify_offers' => true,
        ]);
    }

    public function test_marketing_toggle_writes_to_the_existing_marketing_consent_column(): void
    {
        $user = User::factory()->create(['marketing_consent' => false]);

        $this->actingAs($user)
            ->patchJson('/api/auth/notification-preferences', ['notify_marketing' => true])
            ->assertOk()
            ->assertJsonPath('data.notify_marketing', true);

        $this->assertDatabaseHas('users', ['id' => $user->id, 'marketing_consent' => true]);
    }

    public function test_notification_preferences_default_to_true_for_a_new_user(): void
    {
        // ->fresh(): the factory's in-memory model never had the columns'
        // DB-level defaults loaded into it post-insert — a real request
        // always re-reads the user from the DB via its Sanctum token, so
        // this reflects what actually happens on a live "am I opted in?" check.
        $user = User::factory()->create()->fresh();

        $this->actingAs($user)->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('data.notify_orders', true)
            ->assertJsonPath('data.notify_messages', true)
            ->assertJsonPath('data.notify_offers', true);
    }

    public function test_requesting_a_phone_change_sends_an_otp_to_the_new_number(): void
    {
        $user = User::factory()->create(['phone' => '+255754111111']);

        $this->actingAs($user)
            ->postJson('/api/auth/phone/change/request', ['phone' => '+255754222222'])
            ->assertOk();

        $this->assertMatchesRegularExpression('/^\d{6}$/', Cache::get('otp:+255754222222'));
    }

    public function test_requesting_a_phone_change_to_the_current_number_is_rejected(): void
    {
        $user = User::factory()->create(['phone' => '+255754111111']);

        $this->actingAs($user)
            ->postJson('/api/auth/phone/change/request', ['phone' => '+255754111111'])
            ->assertStatus(422);
    }

    public function test_requesting_a_phone_change_to_a_number_already_in_use_is_rejected(): void
    {
        $user = User::factory()->create(['phone' => '+255754111111']);
        User::factory()->create(['phone' => '+255754222222']);

        $this->actingAs($user)
            ->postJson('/api/auth/phone/change/request', ['phone' => '+255754222222'])
            ->assertStatus(422);
    }

    public function test_verifying_the_correct_code_changes_the_phone_number(): void
    {
        $user = User::factory()->create(['phone' => '+255754111111']);
        $newNumber = '+255754222222';

        $this->actingAs($user)->postJson('/api/auth/phone/change/request', ['phone' => $newNumber])->assertOk();
        $code = Cache::get('otp:'.$newNumber);

        $this->actingAs($user)
            ->postJson('/api/auth/phone/change/verify', ['phone' => $newNumber, 'code' => $code])
            ->assertOk()
            ->assertJsonPath('data.phone', $newNumber);

        $this->assertSame($newNumber, $user->fresh()->phone);
    }

    public function test_verifying_the_wrong_code_does_not_change_the_phone_number(): void
    {
        $user = User::factory()->create(['phone' => '+255754111111']);
        $newNumber = '+255754222222';

        $this->actingAs($user)->postJson('/api/auth/phone/change/request', ['phone' => $newNumber])->assertOk();

        $this->actingAs($user)
            ->postJson('/api/auth/phone/change/verify', ['phone' => $newNumber, 'code' => '000000'])
            ->assertStatus(422);

        $this->assertSame('+255754111111', $user->fresh()->phone);
    }

    public function test_phone_change_routes_require_authentication(): void
    {
        $this->postJson('/api/auth/phone/change/request', ['phone' => '+255754222222'])->assertStatus(401);
        $this->postJson('/api/auth/phone/change/verify', ['phone' => '+255754222222', 'code' => '123456'])->assertStatus(401);
    }
}
