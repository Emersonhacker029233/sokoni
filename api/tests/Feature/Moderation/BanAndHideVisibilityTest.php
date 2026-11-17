<?php

namespace Tests\Feature\Moderation;

use App\Models\Comment;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Offer;
use App\Models\Order;
use App\Models\Product;
use App\Models\Review;
use App\Models\SellerProfile;
use App\Models\Showcase;
use App\Models\Update;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Apple Guideline 1.2: the developer removes the content and ejects the
 * user who provided it. A ban (or a suspension that hasn't ended) now
 * hides everything the user posted from every public list; unbanning
 * brings it back. A moderator can also hide a single shop, message,
 * showcase or update.
 */
class BanAndHideVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private SellerProfile $shop;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create();
        $this->shop = SellerProfile::factory()->verified()->create(['user_id' => $this->owner->id, 'handle' => 'bannedshop']);
        $this->product = Product::factory()->create(['seller_id' => $this->shop->id]);
    }

    private function ban(User $user, ?\DateTimeInterface $until = null): void
    {
        $user->forceFill(['banned_at' => now(), 'banned_until' => $until, 'ban_reason' => 'Abuse'])->save();
    }

    private function ids(string $uri): array
    {
        return collect($this->getJson($uri)->assertOk()->json('data'))->pluck('id')->all();
    }

    public function test_a_banned_sellers_shop_and_everything_in_it_disappear(): void
    {
        $showcase = Showcase::factory()->create(['seller_id' => $this->shop->id, 'product_id' => $this->product->id]);
        $update = Update::factory()->create(['seller_id' => $this->shop->id]);
        $offer = Offer::factory()->create(['seller_id' => $this->shop->id, 'product_id' => $this->product->id]);

        $this->ban($this->owner);

        $this->assertNotContains($this->product->id, $this->ids('/api/products'));
        $this->assertNotContains($this->shop->id, $this->ids('/api/sellers'));
        $this->assertNotContains($showcase->id, $this->ids('/api/showcases'));
        $this->assertNotContains($update->id, $this->ids('/api/updates'));
        $this->assertNotContains($offer->id, $this->ids('/api/offers'));
        $this->getJson('/api/products/'.$this->product->id)->assertNotFound();
        $this->getJson('/api/sellers/bannedshop')->assertNotFound();
        $this->assertSame([], collect($this->getJson('/api/feed')->json('data'))->where('type', 'product')->pluck('data.id')->intersect([$this->product->id])->all());
    }

    /** Approved decision 2: the ban ejects the shop from the website too. */
    public function test_a_banned_sellers_shop_and_listing_pages_are_gone_from_the_website(): void
    {
        $this->get('/@bannedshop')->assertOk();
        $this->get('/p/'.$this->product->id)->assertRedirect();

        $this->ban($this->owner);

        $this->get('/@bannedshop')->assertNotFound();
        $this->get('/p/'.$this->product->id)->assertNotFound();
        $this->get('/stores')->assertOk()->assertDontSee('/@bannedshop');
    }

    public function test_comments_and_reviews_by_a_banned_buyer_disappear(): void
    {
        $buyer = User::factory()->create();
        $comment = Comment::factory()->create(['product_id' => $this->product->id, 'user_id' => $buyer->id]);
        $order = Order::factory()->completed()->create(['buyer_id' => $buyer->id, 'seller_id' => $this->shop->id]);
        $review = Review::factory()->create(['order_id' => $order->id, 'buyer_id' => $buyer->id, 'seller_id' => $this->shop->id, 'rating' => 1]);

        $this->ban($buyer);

        $this->assertNotContains($comment->id, $this->ids('/api/products/'.$this->product->id.'/comments'));
        $response = $this->getJson('/api/sellers/bannedshop/reviews')->assertOk();
        $this->assertNotContains($review->id, collect($response->json('data'))->pluck('id'));
        $this->assertSame([], (array) $response->json('meta.distribution'));
    }

    public function test_unbanning_or_an_ended_suspension_restores_the_content(): void
    {
        $this->ban($this->owner, now()->addDay());
        $this->assertNotContains($this->product->id, $this->ids('/api/products'));

        $this->travel(2)->days();
        $this->assertContains($this->product->id, $this->ids('/api/products'));

        $this->ban($this->owner);
        $this->owner->forceFill(['banned_at' => null, 'banned_until' => null, 'ban_reason' => null])->save();
        $this->assertContains($this->product->id, $this->ids('/api/products'));
    }

    public function test_a_hidden_shop_is_gone_for_the_public_but_not_for_its_owner(): void
    {
        $this->shop->forceFill(['is_hidden' => true])->save();

        $this->assertNotContains($this->product->id, $this->ids('/api/products'));
        $this->getJson('/api/sellers/bannedshop')->assertNotFound();

        $this->actingAs($this->owner)->getJson('/api/sellers/bannedshop')->assertOk();
        $this->actingAs($this->owner)->getJson('/api/products/'.$this->product->id)->assertOk();
    }

    public function test_hidden_showcases_and_updates_are_left_out(): void
    {
        $showcase = Showcase::factory()->create(['seller_id' => $this->shop->id, 'product_id' => $this->product->id, 'is_hidden' => true]);
        $update = Update::factory()->create(['seller_id' => $this->shop->id, 'is_hidden' => true]);

        $this->assertNotContains($showcase->id, $this->ids('/api/showcases'));
        $this->assertNotContains($update->id, $this->ids('/api/updates'));
        $this->getJson('/api/showcases/'.$showcase->id)->assertNotFound();
    }

    public function test_a_hidden_message_is_gone_from_the_thread_and_its_preview(): void
    {
        $buyer = User::factory()->create();
        $conversation = Conversation::factory()->create(['buyer_id' => $buyer->id, 'seller_id' => $this->shop->id]);
        $kept = Message::factory()->create(['conversation_id' => $conversation->id, 'sender_id' => $buyer->id, 'body' => 'Is it available?']);
        $hidden = Message::factory()->create(['conversation_id' => $conversation->id, 'sender_id' => $buyer->id, 'body' => 'abusive text']);
        $hidden->forceFill(['is_hidden' => true])->save();

        $thread = collect($this->actingAs($this->owner)->getJson("/api/conversations/{$conversation->id}/messages")->assertOk()->json('data'))->pluck('id');
        $this->assertSame([$kept->id], $thread->all());

        $list = $this->actingAs($this->owner)->getJson('/api/conversations')->assertOk();
        $this->assertSame('Is it available?', $list->json('data.0.last_message'));
    }

    public function test_an_order_for_a_product_that_became_unavailable_is_refused_cleanly(): void
    {
        $this->ban($this->owner);

        $this->actingAs(User::factory()->create())->postJson('/api/orders', [
            'items' => [['product_id' => $this->product->id, 'qty' => 1]],
            'delivery_method' => 'pickup',
            'payment_method' => 'pay_on_pickup',
        ])->assertStatus(422)->assertJsonValidationErrors('items');
    }
}
