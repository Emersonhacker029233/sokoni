<?php

namespace Tests\Feature\Web;

use App\Models\Conversation;
use App\Models\Product;
use App\Models\SellerProfile;
use App\Models\User;
use App\Support\Legal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * End-to-end coverage for tester feedback A2: the "Message" button on
 * product/shop pages existed but only ever linked to the (often empty)
 * conversation list — there was no way to actually start a thread with a
 * seller from the website at all. `MessagesController::start()` is the
 * fix; these tests cover the full path it diagnosis called for: the
 * button/route existing, login being required, the conversation actually
 * being created, and the thread being able to send and poll afterwards.
 */
class MessagingTest extends TestCase
{
    use RefreshDatabase;

    private function onboardedBuyer(): User
    {
        return User::factory()->create([
            'terms_accepted_at' => now(),
            'terms_version' => Legal::TERMS_VERSION,
            'account_intent' => 'buy',
        ]);
    }

    public function test_the_product_page_shows_a_working_message_form_pointing_at_the_real_seller_and_product(): void
    {
        $seller = SellerProfile::factory()->verified()->create();
        $product = Product::factory()->create(['seller_id' => $seller->id]);

        $response = $this->actingAsWebUser($this->onboardedBuyer())->get(
            route('web.product', ['product' => $product->id, 'slug' => \Illuminate\Support\Str::slug($product->title)])
        );

        $response->assertOk();
        $response->assertSee(route('web.account.messages.start'), false);
        $response->assertSee('value="'.$seller->id.'"', false);
        $response->assertSee('value="'.$product->id.'"', false);
    }

    public function test_a_signed_in_buyer_can_start_a_conversation_from_a_product_page(): void
    {
        $buyer = $this->onboardedBuyer();
        $seller = SellerProfile::factory()->verified()->create();
        $product = Product::factory()->create(['seller_id' => $seller->id]);

        $response = $this->actingAsWebUser($buyer)->post(route('web.account.messages.start'), [
            'seller_id' => $seller->id,
            'product_id' => $product->id,
        ]);

        $conversation = Conversation::where('buyer_id', $buyer->id)->where('seller_id', $seller->id)->firstOrFail();
        $response->assertRedirect(route('web.account.messages.show', $conversation));
        $this->assertSame($product->id, $conversation->product_id);
    }

    public function test_starting_a_conversation_twice_from_the_same_product_resumes_the_same_thread(): void
    {
        $buyer = $this->onboardedBuyer();
        $seller = SellerProfile::factory()->verified()->create();
        $product = Product::factory()->create(['seller_id' => $seller->id]);

        $this->actingAsWebUser($buyer)->post(route('web.account.messages.start'), [
            'seller_id' => $seller->id,
            'product_id' => $product->id,
        ]);
        $this->actingAsWebUser($buyer)->post(route('web.account.messages.start'), [
            'seller_id' => $seller->id,
            'product_id' => $product->id,
        ]);

        $this->assertSame(1, Conversation::where('buyer_id', $buyer->id)->where('seller_id', $seller->id)->count());
    }

    public function test_a_buyer_can_start_a_conversation_from_a_shop_page_without_a_product(): void
    {
        $buyer = $this->onboardedBuyer();
        $seller = SellerProfile::factory()->verified()->create();

        $response = $this->actingAsWebUser($buyer)->post(route('web.account.messages.start'), [
            'seller_id' => $seller->id,
        ]);

        $conversation = Conversation::where('buyer_id', $buyer->id)->where('seller_id', $seller->id)->firstOrFail();
        $response->assertRedirect(route('web.account.messages.show', $conversation));
        $this->assertNull($conversation->product_id);
    }

    public function test_a_signed_out_visitor_is_sent_to_login_when_starting_a_conversation(): void
    {
        $seller = SellerProfile::factory()->verified()->create();

        $this->post(route('web.account.messages.start'), ['seller_id' => $seller->id])
            ->assertRedirect(route('web.login'));
    }

    public function test_a_buyer_can_send_a_message_in_the_thread_and_it_appears_on_poll(): void
    {
        $buyer = $this->onboardedBuyer();
        $conversation = Conversation::factory()->create(['buyer_id' => $buyer->id]);

        $this->actingAsWebUser($buyer)
            ->post(route('web.account.messages.store', $conversation), ['body' => 'Is this still available?'])
            ->assertRedirect(route('web.account.messages.show', $conversation));

        $this->assertDatabaseHas('messages', [
            'conversation_id' => $conversation->id,
            'sender_id' => $buyer->id,
            'body' => 'Is this still available?',
        ]);

        $poll = $this->actingAsWebUser($buyer)->getJson(route('web.account.messages.poll', $conversation));
        $poll->assertOk();
        $poll->assertJsonFragment(['body' => 'Is this still available?']);
    }

    /**
     * A4 (tester feedback): "no new-message notification on desktop" — the
     * unread badge existed only in the mobile bottom nav's own markup,
     * computed once per page load; the desktop header's Chats link never
     * had one at all, and neither surface updated without a navigation.
     * These cover the new shared endpoint both badges now poll, and that
     * both surfaces actually render the (initial, server-rendered) count.
     */
    public function test_the_unread_count_endpoint_reports_messages_from_others_not_yet_read(): void
    {
        $buyer = $this->onboardedBuyer();
        $conversation = Conversation::factory()->create(['buyer_id' => $buyer->id]);
        \App\Models\Message::factory()->create(['conversation_id' => $conversation->id, 'sender_id' => $conversation->seller->user_id, 'read_at' => null]);
        \App\Models\Message::factory()->create(['conversation_id' => $conversation->id, 'sender_id' => $buyer->id, 'read_at' => null]);

        $response = $this->actingAsWebUser($buyer)->getJson(route('web.account.messages.unread-count'));

        $response->assertOk();
        // Only the other party's message counts — the buyer's own sent
        // message is never "unread" to the buyer.
        $response->assertJson(['count' => 1]);
    }

    public function test_the_desktop_header_shows_the_same_unread_badge_the_mobile_bottom_nav_shows(): void
    {
        $buyer = $this->onboardedBuyer();
        $conversation = Conversation::factory()->create(['buyer_id' => $buyer->id]);
        \App\Models\Message::factory()->create(['conversation_id' => $conversation->id, 'sender_id' => $conversation->seller->user_id, 'read_at' => null]);

        $response = $this->actingAsWebUser($buyer)->get(route('web.home'));

        $response->assertOk();
        // Both surfaces are rendered on every page (one hidden per
        // breakpoint via CSS, not conditionally in Blade) — the count "1"
        // must appear at least twice: once in the desktop header's badge,
        // once in the mobile bottom nav's.
        $response->assertSeeInOrder(['display: inline-flex', '>1<'], false);
        $response->assertSeeInOrder(['display: flex', '>1<'], false);
    }

    public function test_a_signed_out_visitor_gets_no_unread_polling(): void
    {
        $response = $this->get(route('web.home'));

        $response->assertOk();
        $response->assertSee('messageNotifier(0,', false);
        $response->assertSee(', false)', false);
    }
}
