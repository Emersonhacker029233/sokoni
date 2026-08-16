<?php

namespace Tests\Feature\Social;

use App\Models\Product;
use App\Models\SellerProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommentTest extends TestCase
{
    use RefreshDatabase;

    public function test_anyone_can_list_a_products_root_comments_with_their_replies(): void
    {
        $product = Product::factory()->create();
        $root = $product->comments()->create(['user_id' => User::factory()->create()->id, 'body' => 'Nice!']);
        $root->replies()->create(['product_id' => $product->id, 'user_id' => User::factory()->create()->id, 'body' => 'Agreed']);

        $response = $this->getJson("/api/products/{$product->id}/comments")->assertOk();

        $response->assertJsonCount(1, 'data');
        $response->assertJsonCount(1, 'data.0.replies');
    }

    public function test_a_signed_in_user_can_comment_on_a_product(): void
    {
        $product = Product::factory()->create();
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson("/api/products/{$product->id}/comments", ['body' => 'How much for two?'])
            ->assertCreated()
            ->assertJsonPath('data.body', 'How much for two?');

        $this->assertDatabaseHas('comments', ['product_id' => $product->id, 'user_id' => $user->id]);
    }

    public function test_commenting_requires_authentication(): void
    {
        $product = Product::factory()->create();

        $this->postJson("/api/products/{$product->id}/comments", ['body' => 'Hello'])
            ->assertStatus(401);
    }

    public function test_a_reply_can_be_posted_to_a_root_comment(): void
    {
        $product = Product::factory()->create();
        $root = $product->comments()->create(['user_id' => User::factory()->create()->id, 'body' => 'Nice!']);
        $replier = User::factory()->create();

        $this->actingAs($replier)
            ->postJson("/api/products/{$product->id}/comments", ['body' => 'Thanks!', 'parent_id' => $root->id])
            ->assertCreated();

        $this->assertDatabaseHas('comments', ['parent_id' => $root->id, 'body' => 'Thanks!']);
    }

    public function test_replies_cannot_be_nested_more_than_one_level_deep(): void
    {
        $product = Product::factory()->create();
        $root = $product->comments()->create(['user_id' => User::factory()->create()->id, 'body' => 'Nice!']);
        $reply = $root->replies()->create(['product_id' => $product->id, 'user_id' => User::factory()->create()->id, 'body' => 'Agreed']);

        $this->actingAs(User::factory()->create())
            ->postJson("/api/products/{$product->id}/comments", ['body' => 'Me too', 'parent_id' => $reply->id])
            ->assertStatus(422);
    }

    public function test_a_parent_comment_from_a_different_product_is_rejected(): void
    {
        $productA = Product::factory()->create();
        $productB = Product::factory()->create();
        $root = $productA->comments()->create(['user_id' => User::factory()->create()->id, 'body' => 'Nice!']);

        $this->actingAs(User::factory()->create())
            ->postJson("/api/products/{$productB->id}/comments", ['body' => 'Wrong thread', 'parent_id' => $root->id])
            ->assertStatus(422);
    }

    public function test_the_comment_author_can_delete_their_own_comment(): void
    {
        $product = Product::factory()->create();
        $user = User::factory()->create();
        $comment = $product->comments()->create(['user_id' => $user->id, 'body' => 'Delete me']);

        $this->actingAs($user)
            ->deleteJson("/api/comments/{$comment->id}")
            ->assertOk();

        $this->assertDatabaseMissing('comments', ['id' => $comment->id]);
    }

    public function test_a_stranger_cannot_delete_someone_elses_comment(): void
    {
        $product = Product::factory()->create();
        $comment = $product->comments()->create(['user_id' => User::factory()->create()->id, 'body' => 'Mine']);

        $this->actingAs(User::factory()->create())
            ->deleteJson("/api/comments/{$comment->id}")
            ->assertStatus(403);
    }

    public function test_the_products_own_seller_can_delete_a_comment_on_their_listing(): void
    {
        $sellerUser = User::factory()->create();
        $seller = SellerProfile::factory()->verified()->create(['user_id' => $sellerUser->id]);
        $product = Product::factory()->create(['seller_id' => $seller->id]);
        $comment = $product->comments()->create(['user_id' => User::factory()->create()->id, 'body' => 'A question']);

        $this->actingAs($sellerUser)
            ->deleteJson("/api/comments/{$comment->id}")
            ->assertOk();
    }
}
