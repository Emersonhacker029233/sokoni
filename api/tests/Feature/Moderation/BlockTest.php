<?php

namespace Tests\Feature\Moderation;

use App\Models\AppNotification;
use App\Models\Comment;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Offer;
use App\Models\Order;
use App\Models\Product;
use App\Models\Report;
use App\Models\Review;
use App\Models\SellerProfile;
use App\Models\Showcase;
use App\Models\Update;
use App\Models\User;
use App\Models\UserBlock;
use App\Notifications\NewReportNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Apple Guideline 1.2: "a mechanism for users to block abusive users.
 * Blocking should also notify the developer of the inappropriate content
 * and should remove it from the user's feed instantly."
 */
class BlockTest extends TestCase
{
    use RefreshDatabase;

    private User $blocker;

    private User $abuser;

    private SellerProfile $abusersShop;

    private Product $abusersProduct;

    private SellerProfile $otherShop;

    private Product $otherProduct;

    protected function setUp(): void
    {
        parent::setUp();

        $this->blocker = User::factory()->create();
        $this->abuser = User::factory()->create(['name' => 'Abusive Seller']);
        $this->abusersShop = SellerProfile::factory()->verified()->create(['user_id' => $this->abuser->id, 'handle' => 'abuser']);
        $this->abusersProduct = Product::factory()->create(['seller_id' => $this->abusersShop->id, 'title' => 'Abusive listing']);
        $this->otherShop = SellerProfile::factory()->verified()->create(['handle' => 'goodshop']);
        $this->otherProduct = Product::factory()->create(['seller_id' => $this->otherShop->id, 'title' => 'Fine listing']);
    }

    private function block(?User $who = null, array $payload = []): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($who ?? $this->blocker)
            ->postJson('/api/blocks', $payload ?: ['seller_id' => $this->abusersShop->id]);
    }

    /** @return list<int> */
    private function productIds(\Illuminate\Testing\TestResponse $response): array
    {
        return collect($response->json('data'))->pluck('id')->all();
    }

    public function test_blocking_a_shop_removes_its_products_from_the_blockers_lists_only(): void
    {
        $this->block()->assertCreated()
            ->assertJsonPath('data.user_id', $this->abuser->id)
            ->assertJsonPath('data.seller_id', $this->abusersShop->id);

        $mine = $this->actingAs($this->blocker)->getJson('/api/products')->assertOk();
        $this->assertNotContains($this->abusersProduct->id, $this->productIds($mine));
        $this->assertContains($this->otherProduct->id, $this->productIds($mine));

        $search = $this->actingAs($this->blocker)->getJson('/api/products?q=listing')->assertOk();
        $this->assertSame([$this->otherProduct->id], $this->productIds($search));

        $feed = $this->actingAs($this->blocker)->getJson('/api/feed')->assertOk();
        $feedProductIds = collect($feed->json('data'))->where('type', 'product')->pluck('data.id')->all();
        $this->assertNotContains($this->abusersProduct->id, $feedProductIds);
        $this->assertContains($this->otherProduct->id, $feedProductIds);

        // Everyone else still sees it.
        $someoneElse = $this->actingAs(User::factory()->create())->getJson('/api/products')->assertOk();
        $this->assertContains($this->abusersProduct->id, $this->productIds($someoneElse));
    }

    /**
     * The app sends a bearer token, and these discovery routes have no
     * auth middleware. This proves the filter reads the token (sanctum
     * guard), which is what happens on a real phone, not just actingAs().
     */
    public function test_public_routes_filter_for_a_bearer_token_user(): void
    {
        UserBlock::query()->create(['blocker_id' => $this->blocker->id, 'blocked_id' => $this->abuser->id]);
        $token = $this->blocker->createToken('sokoni-app')->plainTextToken;

        $response = $this->withToken($token)->getJson('/api/products')->assertOk();

        $this->assertNotContains($this->abusersProduct->id, $this->productIds($response));
        $this->assertContains($this->otherProduct->id, $this->productIds($response));
    }

    public function test_the_blocked_shop_and_its_product_answer_with_a_blocked_state(): void
    {
        $this->block();

        $this->actingAs($this->blocker)->getJson('/api/products/'.$this->abusersProduct->id)
            ->assertForbidden()
            ->assertJsonPath('code', 'blocked')
            ->assertJsonPath('blocked_user_id', $this->abuser->id);

        $this->actingAs($this->blocker)->getJson('/api/sellers/abuser')
            ->assertForbidden()
            ->assertJsonPath('code', 'blocked');

        $sellers = $this->actingAs($this->blocker)->getJson('/api/sellers')->assertOk();
        $this->assertNotContains($this->abusersShop->id, collect($sellers->json('data'))->pluck('id')->all());
    }

    public function test_comments_reviews_showcases_updates_and_offers_by_the_blocked_user_disappear(): void
    {
        // The abuser also writes as a buyer: a comment on someone else's
        // product and a review of someone else's shop.
        $comment = Comment::factory()->create(['product_id' => $this->otherProduct->id, 'user_id' => $this->abuser->id, 'body' => 'insult']);
        $fineComment = Comment::factory()->create(['product_id' => $this->otherProduct->id, 'body' => 'nice']);
        $order = Order::factory()->completed()->create(['buyer_id' => $this->abuser->id, 'seller_id' => $this->otherShop->id]);
        $review = Review::factory()->create(['order_id' => $order->id, 'buyer_id' => $this->abuser->id, 'seller_id' => $this->otherShop->id]);
        $showcase = Showcase::factory()->create(['seller_id' => $this->abusersShop->id, 'product_id' => $this->abusersProduct->id]);
        $update = Update::factory()->create(['seller_id' => $this->abusersShop->id, 'product_id' => null]);
        $offer = Offer::factory()->create(['seller_id' => $this->abusersShop->id, 'product_id' => $this->abusersProduct->id]);

        $this->block(payload: ['user_id' => $this->abuser->id])->assertCreated();
        $as = $this->actingAs($this->blocker);

        $comments = collect($as->getJson('/api/products/'.$this->otherProduct->id.'/comments')->json('data'))->pluck('id');
        $this->assertNotContains($comment->id, $comments);
        $this->assertContains($fineComment->id, $comments);

        $reviews = collect($as->getJson('/api/sellers/goodshop/reviews')->json('data'))->pluck('id');
        $this->assertNotContains($review->id, $reviews);

        $this->assertNotContains($showcase->id, collect($as->getJson('/api/showcases')->json('data'))->pluck('id'));
        $this->assertNotContains($update->id, collect($as->getJson('/api/updates')->json('data'))->pluck('id'));
        $this->assertNotContains($offer->id, collect($as->getJson('/api/offers')->json('data'))->pluck('id'));
        $as->getJson('/api/showcases/'.$showcase->id)->assertForbidden()->assertJsonPath('code', 'blocked');

        // A guest still sees all of it.
        $this->app['auth']->forgetGuards();
        $this->assertContains($comment->id, collect($this->getJson('/api/products/'.$this->otherProduct->id.'/comments')->json('data'))->pluck('id'));
        $this->assertContains($review->id, collect($this->getJson('/api/sellers/goodshop/reviews')->json('data'))->pluck('id'));
    }

    public function test_a_block_files_a_report_and_alerts_the_moderators(): void
    {
        Notification::fake();
        config(['sokoni.moderation_alert_email' => 'moderation@sokoni.co.tz']);

        $this->block(payload: ['seller_id' => $this->abusersShop->id, 'reason' => 'harassment'])->assertCreated();

        $report = Report::query()->sole();
        $this->assertSame(User::class, $report->reportable_type);
        $this->assertSame($this->abuser->id, $report->reportable_id);
        $this->assertSame('blocked', $report->reason);
        $this->assertSame('pending', $report->fresh()->status);
        $this->assertNotNull($report->fresh()->alerted_at);

        Notification::assertSentOnDemand(
            NewReportNotification::class,
            fn ($notification, $channels, $notifiable) => $notifiable->routes['mail'] === ['moderation@sokoni.co.tz'],
        );
    }

    public function test_blocking_twice_files_one_report_and_keeps_one_block(): void
    {
        $this->block()->assertCreated();
        $this->block()->assertOk();

        $this->assertSame(1, UserBlock::query()->count());
        $this->assertSame(1, Report::query()->count());
    }

    public function test_you_cannot_block_yourself(): void
    {
        $this->block(payload: ['user_id' => $this->blocker->id])->assertStatus(422);
        $this->assertSame(0, UserBlock::query()->count());
    }

    public function test_blocking_requires_an_account(): void
    {
        $this->postJson('/api/blocks', ['seller_id' => $this->abusersShop->id])->assertUnauthorized();
    }

    public function test_blocking_a_shop_unfollows_it(): void
    {
        Customer::query()->create(['buyer_id' => $this->blocker->id, 'seller_id' => $this->abusersShop->id]);

        $this->block()->assertCreated();

        $this->assertDatabaseMissing('customers', ['buyer_id' => $this->blocker->id, 'seller_id' => $this->abusersShop->id]);
    }

    public function test_messages_and_new_conversations_stop_in_both_directions(): void
    {
        $conversation = Conversation::factory()->create(['buyer_id' => $this->blocker->id, 'seller_id' => $this->abusersShop->id]);
        $this->block()->assertCreated();

        // The blocker can't write to them...
        $this->actingAs($this->blocker)
            ->postJson("/api/conversations/{$conversation->id}/messages", ['body' => 'hi'])
            ->assertForbidden()
            ->assertJsonPath('code', 'blocked');

        // ...and they can't write to the blocker, nor start a new thread.
        $this->actingAs($this->abuser)
            ->postJson("/api/conversations/{$conversation->id}/messages", ['body' => 'you there?'])
            ->assertForbidden();
        $otherShopOfBlocker = SellerProfile::factory()->verified()->create(['user_id' => $this->blocker->id]);
        $this->actingAs($this->abuser)
            ->postJson('/api/conversations', ['seller_id' => $otherShopOfBlocker->id])
            ->assertForbidden();

        $this->assertSame(0, $conversation->messages()->count());
    }

    public function test_the_conversation_disappears_from_the_blockers_chat_list(): void
    {
        $blocked = Conversation::factory()->create(['buyer_id' => $this->blocker->id, 'seller_id' => $this->abusersShop->id]);
        $kept = Conversation::factory()->create(['buyer_id' => $this->blocker->id, 'seller_id' => $this->otherShop->id]);
        $this->block();

        $ids = collect($this->actingAs($this->blocker)->getJson('/api/conversations')->assertOk()->json('data'))->pluck('id');

        $this->assertNotContains($blocked->id, $ids);
        $this->assertContains($kept->id, $ids);
        $this->actingAs($this->blocker)->getJson("/api/conversations/{$blocked->id}/messages")->assertForbidden();
    }

    public function test_orders_stop_in_both_directions(): void
    {
        $this->block()->assertCreated();

        $this->actingAs($this->blocker)->postJson('/api/orders', [
            'items' => [['product_id' => $this->abusersProduct->id, 'qty' => 1]],
            'delivery_method' => 'pickup',
            'payment_method' => 'pay_on_pickup',
        ])->assertForbidden()->assertJsonPath('code', 'blocked');

        // A shop that blocked a buyer gets no orders from them either.
        $buyer = User::factory()->create();
        $this->actingAs($this->abuser)->postJson('/api/blocks', ['user_id' => $buyer->id])->assertCreated();
        $this->actingAs($buyer)->postJson('/api/orders', [
            'items' => [['product_id' => $this->abusersProduct->id, 'qty' => 1]],
            'delivery_method' => 'pickup',
            'payment_method' => 'pay_on_pickup',
        ])->assertForbidden();

        $this->assertSame(0, Order::query()->count());
    }

    public function test_notifications_from_a_blocked_user_are_hidden_and_new_ones_are_not_sent(): void
    {
        $conversation = Conversation::factory()->create(['buyer_id' => $this->abuser->id, 'seller_id' => $this->otherShop->id]);
        $seller = $this->otherShop->user;

        // Before the block: the message notification arrives, tagged with its sender.
        $this->actingAs($this->abuser)->postJson("/api/conversations/{$conversation->id}/messages", ['body' => 'first'])->assertCreated();
        $this->assertSame($this->abuser->id, AppNotification::query()->where('user_id', $seller->id)->sole()->actor_id);
        AppNotification::factory()->create(['user_id' => $seller->id, 'title' => 'System notice']);

        $this->actingAs($seller)->postJson('/api/blocks', ['user_id' => $this->abuser->id])->assertCreated();

        $titles = collect($this->actingAs($seller)->getJson('/api/notifications')->assertOk()->json('data'))->pluck('title');
        $this->assertSame(['System notice'], $titles->all());
    }

    public function test_unblocking_brings_everything_back(): void
    {
        $this->block();
        $this->actingAs($this->blocker)->deleteJson('/api/blocks/'.$this->abuser->id)->assertOk();

        $this->assertContains(
            $this->abusersProduct->id,
            $this->productIds($this->actingAs($this->blocker)->getJson('/api/products')),
        );
        $this->actingAs($this->blocker)->getJson('/api/sellers/abuser')->assertOk();
    }

    public function test_the_block_list_shows_who_without_private_details(): void
    {
        $this->block();

        $row = $this->actingAs($this->blocker)->getJson('/api/blocks')->assertOk()->json('data.0');

        $this->assertSame($this->abuser->id, $row['user_id']);
        $this->assertSame('Abusive Seller', $row['name']);
        $this->assertSame('abuser', $row['handle']);
        $this->assertArrayNotHasKey('phone', $row);
        $this->assertArrayNotHasKey('email', $row);
    }
}
