<?php

namespace Tests\Feature\Chat;

use App\Models\Conversation;
use App\Models\SellerProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChatTest extends TestCase
{
    use RefreshDatabase;

    public function test_buyer_can_start_a_conversation_with_a_seller(): void
    {
        $buyer = User::factory()->create();
        $seller = SellerProfile::factory()->verified()->create();

        $this->actingAs($buyer)->postJson('/api/conversations', ['seller_id' => $seller->id])
            ->assertCreated();

        $this->assertDatabaseHas('conversations', ['buyer_id' => $buyer->id, 'seller_id' => $seller->id]);
    }

    public function test_starting_the_same_conversation_twice_reuses_it(): void
    {
        $buyer = User::factory()->create();
        $seller = SellerProfile::factory()->verified()->create();

        $first = $this->actingAs($buyer)->postJson('/api/conversations', ['seller_id' => $seller->id])->json('data.id');
        $second = $this->actingAs($buyer)->postJson('/api/conversations', ['seller_id' => $seller->id])->json('data.id');

        $this->assertEquals($first, $second);
    }

    public function test_sending_a_message_marks_it_unread_for_the_other_party(): void
    {
        $conversation = Conversation::factory()->create();

        $this->actingAs($conversation->buyer)
            ->postJson("/api/conversations/{$conversation->id}/messages", ['body' => 'Hello!'])
            ->assertCreated();

        $this->assertDatabaseHas('messages', ['conversation_id' => $conversation->id, 'body' => 'Hello!', 'read_at' => null]);
    }

    public function test_fetching_messages_marks_the_other_partys_messages_as_read(): void
    {
        $conversation = Conversation::factory()->create();
        $this->actingAs($conversation->buyer)
            ->postJson("/api/conversations/{$conversation->id}/messages", ['body' => 'Hello!']);

        $this->actingAs($conversation->seller->user)
            ->getJson("/api/conversations/{$conversation->id}/messages")
            ->assertOk();

        $this->assertDatabaseMissing('messages', ['conversation_id' => $conversation->id, 'read_at' => null]);
    }

    public function test_a_stranger_cannot_read_someone_elses_conversation(): void
    {
        $conversation = Conversation::factory()->create();
        $stranger = User::factory()->create();

        $this->actingAs($stranger)
            ->getJson("/api/conversations/{$conversation->id}/messages")
            ->assertForbidden();
    }
}
