<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\Reviews\Pages\ListReviews;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Review;
use App\Models\SellerProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ReviewResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_admin_can_load_reviews(): void
    {
        $admin = User::factory()->admin()->create();
        Review::factory()->count(3)->create();

        $this->actingAsAdmin($admin)->get('/admin/reviews')->assertOk();
    }

    public function test_reviews_can_be_filtered_by_rating(): void
    {
        $admin = User::factory()->admin()->create();
        $five = Review::factory()->create(['rating' => 5]);
        $two = Review::factory()->create(['rating' => 2]);

        $this->actingAsAdmin($admin);

        Livewire::test(ListReviews::class)
            ->filterTable('rating', 5)
            ->assertCanSeeTableRecords([$five])
            ->assertCanNotSeeTableRecords([$two]);
    }

    public function test_an_admin_can_hide_an_abusive_review_and_it_drops_out_of_the_public_rating(): void
    {
        $admin = User::factory()->admin()->create();
        $seller = SellerProfile::factory()->verified()->create();
        $order1 = Order::factory()->create(['seller_id' => $seller->id, 'status' => 'completed']);
        $order2 = Order::factory()->create(['seller_id' => $seller->id, 'status' => 'completed']);
        $good = Review::factory()->create(['seller_id' => $seller->id, 'order_id' => $order1->id, 'rating' => 5]);
        $bad = Review::factory()->create(['seller_id' => $seller->id, 'order_id' => $order2->id, 'rating' => 1]);

        $this->assertEquals(3.0, $seller->fresh()->rating_avg);

        $this->actingAsAdmin($admin);

        Livewire::test(ListReviews::class)
            ->callTableAction('hide', $bad)
            ->assertHasNoTableActionErrors();

        $this->assertTrue($bad->fresh()->is_hidden);
        $this->assertEquals(5.0, $seller->fresh()->rating_avg);
        $this->assertEquals(1, $seller->fresh()->rating_count);
    }

    public function test_a_hidden_review_does_not_appear_in_the_public_seller_review_list(): void
    {
        $seller = SellerProfile::factory()->verified()->create();
        $order = Order::factory()->create(['seller_id' => $seller->id]);
        $hidden = Review::factory()->create(['seller_id' => $seller->id, 'order_id' => $order->id, 'is_hidden' => true]);

        $response = $this->getJson("/api/sellers/{$seller->handle}/reviews")->assertOk();
        $ids = collect($response->json('data'))->pluck('id');

        $this->assertFalse($ids->contains($hidden->id));
    }

    public function test_the_review_product_summary_reflects_the_orders_items(): void
    {
        $order = Order::factory()->create();
        OrderItem::factory()->create(['order_id' => $order->id, 'title_snapshot' => 'Blue Shirt']);
        $review = Review::factory()->create(['order_id' => $order->id]);

        $summary = \App\Filament\Resources\Reviews\ReviewProductSummary::for($review->fresh('order.items'));

        $this->assertSame('Blue Shirt', $summary);
    }
}
