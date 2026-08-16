<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Push\PushNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_sending_a_push_also_persists_an_in_app_notification(): void
    {
        $user = User::factory()->create();

        app(PushNotifier::class)->notify($user, 'Order placed', 'Your order #123 was placed.');

        $this->assertDatabaseHas('app_notifications', [
            'user_id' => $user->id,
            'title' => 'Order placed',
            'body' => 'Your order #123 was placed.',
        ]);
    }

    public function test_a_user_can_list_their_own_notifications(): void
    {
        $user = User::factory()->create();
        app(PushNotifier::class)->notify($user, 'Hello', 'World');

        $response = $this->actingAs($user)->getJson('/api/notifications')->assertOk();

        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.is_read', false);
    }

    public function test_listing_notifications_requires_authentication(): void
    {
        $this->getJson('/api/notifications')->assertStatus(401);
    }

    public function test_a_user_can_mark_a_single_notification_read(): void
    {
        $user = User::factory()->create();
        app(PushNotifier::class)->notify($user, 'Hello', 'World');
        $notification = $user->appNotifications()->first();

        $this->actingAs($user)
            ->patchJson("/api/notifications/{$notification->id}/read")
            ->assertOk()
            ->assertJsonPath('data.is_read', true);
    }

    public function test_a_stranger_cannot_mark_someone_elses_notification_read(): void
    {
        $owner = User::factory()->create();
        app(PushNotifier::class)->notify($owner, 'Hello', 'World');
        $notification = $owner->appNotifications()->first();

        $this->actingAs(User::factory()->create())
            ->patchJson("/api/notifications/{$notification->id}/read")
            ->assertStatus(403);
    }

    public function test_a_user_can_mark_all_notifications_read(): void
    {
        $user = User::factory()->create();
        app(PushNotifier::class)->notify($user, 'One', 'First');
        app(PushNotifier::class)->notify($user, 'Two', 'Second');

        $this->actingAs($user)->patchJson('/api/notifications/read-all')->assertOk();

        $this->assertSame(0, $user->appNotifications()->unread()->count());
    }
}
