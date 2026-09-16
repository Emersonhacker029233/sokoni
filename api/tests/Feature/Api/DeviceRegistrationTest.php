<?php

namespace Tests\Feature\Api;

use App\Models\Device;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Part 5 (client feedback): "Push notification tokens must follow the
 * active account correctly, so notifications aren't delivered to the
 * wrong one." A device switching which app account is active re-sends
 * its FCM token via the same /devices endpoint under whichever token is
 * now active — this must reassign the row, never leave a second one
 * under the previous account with the identical fcm_token.
 */
class DeviceRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registering_a_device_creates_it_for_the_signed_in_user(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/devices', ['fcm_token' => 'token-abc', 'platform' => 'android'])
            ->assertOk();

        $this->assertDatabaseHas('devices', ['user_id' => $user->id, 'fcm_token' => 'token-abc']);
    }

    public function test_re_registering_the_same_token_for_a_different_user_reassigns_it_rather_than_duplicating(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $this->actingAs($userA)
            ->postJson('/api/devices', ['fcm_token' => 'shared-device-token', 'platform' => 'android'])
            ->assertOk();
        $this->assertDatabaseHas('devices', ['user_id' => $userA->id, 'fcm_token' => 'shared-device-token']);

        // Part 5's actual scenario: switching the active account on the
        // same physical device re-registers the identical FCM token.
        $this->actingAs($userB)
            ->postJson('/api/devices', ['fcm_token' => 'shared-device-token', 'platform' => 'android'])
            ->assertOk();

        // Exactly one row for this token, now owned by userB — not a
        // second row left behind under userA (which would have
        // delivered every future push to both accounts on one device).
        $this->assertSame(1, Device::where('fcm_token', 'shared-device-token')->count());
        $this->assertDatabaseHas('devices', ['user_id' => $userB->id, 'fcm_token' => 'shared-device-token']);
        $this->assertDatabaseMissing('devices', ['user_id' => $userA->id, 'fcm_token' => 'shared-device-token']);
    }

    public function test_re_registering_the_same_token_for_the_same_user_updates_in_place(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/devices', ['fcm_token' => 'token-xyz', 'platform' => 'android'])->assertOk();
        $this->actingAs($user)->postJson('/api/devices', ['fcm_token' => 'token-xyz', 'platform' => 'android'])->assertOk();

        $this->assertSame(1, Device::where('fcm_token', 'token-xyz')->count());
    }
}
