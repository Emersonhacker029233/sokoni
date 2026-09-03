<?php

namespace Tests\Feature\Web;

use App\Models\Order;
use App\Models\SellerProfile;
use App\Models\User;
use App\Support\Legal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The website had no way to submit a review at all — only the app did
 * (tester feedback A3). Covers the new web path end to end: the form
 * appearing on a completed, unreviewed order; a real submission creating
 * the Review row with a visible confirmation; and the same anti-fake-
 * review rule the API enforces (order must be completed, one review per
 * order) holding on the web route too.
 */
class ReviewSubmissionTest extends TestCase
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

    public function test_a_completed_unreviewed_order_shows_the_review_form(): void
    {
        $buyer = $this->onboardedBuyer();
        $order = Order::factory()->completed()->create(['buyer_id' => $buyer->id]);

        $response = $this->actingAsWebUser($buyer)->get(route('web.account.orders.show', $order));

        $response->assertOk();
        $response->assertSee(route('web.account.orders.review', $order), false);
    }

    public function test_a_buyer_can_submit_a_review_and_sees_a_confirmation(): void
    {
        $buyer = $this->onboardedBuyer();
        $order = Order::factory()->completed()->create(['buyer_id' => $buyer->id]);

        $response = $this->actingAsWebUser($buyer)->post(route('web.account.orders.review', $order), [
            'rating' => 5,
            'comment' => 'Great seller, fast delivery.',
        ]);

        $response->assertRedirect(route('web.account.orders.show', $order));
        $this->assertDatabaseHas('reviews', [
            'order_id' => $order->id,
            'buyer_id' => $buyer->id,
            'seller_id' => $order->seller_id,
            'rating' => 5,
        ]);

        // The redirect target renders the flash — this is what "no manual
        // refresh needed" actually means for a server-rendered page.
        $this->followRedirects($response)->assertSee(__('site.review_submitted'));
    }

    public function test_a_pending_order_cannot_be_reviewed(): void
    {
        $buyer = $this->onboardedBuyer();
        $order = Order::factory()->create(['buyer_id' => $buyer->id, 'status' => 'pending']);

        $this->actingAsWebUser($buyer)
            ->post(route('web.account.orders.review', $order), ['rating' => 5])
            ->assertSessionHasErrors('order');

        $this->assertDatabaseMissing('reviews', ['order_id' => $order->id]);
    }

    public function test_an_order_cannot_be_reviewed_twice(): void
    {
        $buyer = $this->onboardedBuyer();
        $order = Order::factory()->completed()->create(['buyer_id' => $buyer->id]);

        $this->actingAsWebUser($buyer)->post(route('web.account.orders.review', $order), ['rating' => 4]);
        $this->actingAsWebUser($buyer)->post(route('web.account.orders.review', $order), ['rating' => 2])
            ->assertSessionHasErrors('order');

        $this->assertSame(1, \App\Models\Review::where('order_id', $order->id)->count());
    }

    public function test_a_buyer_cannot_review_someone_elses_order(): void
    {
        $buyer = $this->onboardedBuyer();
        $otherOrder = Order::factory()->completed()->create();

        $this->actingAsWebUser($buyer)
            ->post(route('web.account.orders.review', $otherOrder), ['rating' => 5])
            ->assertForbidden();
    }
}
