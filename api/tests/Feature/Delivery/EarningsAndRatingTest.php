<?php

namespace Tests\Feature\Delivery;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EarningsAndRatingTest extends TestCase
{
    use DeliveryTestHelpers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpDelivery();
    }

    private function deliveredJobFor($driver, ?User $buyer = null)
    {
        $job = $this->paidJob($buyer);
        $this->asDriver($driver)->postJson("/api/driver/offers/{$job->id}/accept")->assertOk();
        $job = $this->driveToArrived($job, $driver);
        $this->asDriver($driver)->postJson("/api/driver/jobs/{$job->id}/status", ['status' => 'delivered', 'pin' => $job->delivery_pin])->assertOk();

        return $job->fresh();
    }

    public function test_daily_and_weekly_earnings_with_commission_and_balance(): void
    {
        $driver = $this->driverAt(1);
        $a = $this->deliveredJobFor($driver);
        $b = $this->deliveredJobFor($driver, User::factory()->create());

        // A buyer cancellation after assignment earns the cancellation fee too.
        $c = $this->paidJob(User::factory()->create());
        $this->asDriver($driver)->postJson("/api/driver/offers/{$c->id}/accept")->assertOk();
        $this->actingAs($c->order->buyer)->postJson("/api/delivery/orders/{$c->order_id}/cancel")->assertOk();

        $data = $this->asDriver($driver)->getJson('/api/driver/earnings?period=day')->assertOk()->json('data');

        $this->assertSame(3, $data['summary']['jobs_count']);
        $this->assertSame(2, $data['summary']['delivered_count']);
        $this->assertSame($a->fee + $b->fee, $data['summary']['gross_fees']);
        $this->assertSame($a->commission + $b->commission, $data['summary']['commission']);
        $this->assertSame($a->driver_earnings + $b->driver_earnings + 1000, $data['summary']['earnings']);
        $this->assertSame($data['summary']['earnings'], $data['balance']['balance']);
        $this->assertSame(15.0, (float) $data['commission_pct']);

        $week = $this->asDriver($driver)->getJson('/api/driver/earnings?period=week')->assertOk()->json('data.summary');
        $this->assertSame(3, $week['jobs_count']);

        // Another driver sees none of it.
        $other = $this->driverAt(2);
        $this->asDriver($other)->getJson('/api/driver/earnings?period=week')->assertOk()->assertJsonPath('data.summary.jobs_count', 0);
    }

    public function test_jobs_already_paid_out_leave_the_balance(): void
    {
        $driver = $this->driverAt(1);
        $job = $this->deliveredJobFor($driver);
        $payout = $driver->payouts()->create(['amount' => $job->driver_earnings, 'status' => 'paid', 'paid_at' => now(), 'method' => 'mpesa']);
        $job->forceFill(['payout_id' => $payout->id])->save();

        $data = $this->asDriver($driver)->getJson('/api/driver/earnings')->assertOk()->json('data');

        $this->assertSame(0, $data['balance']['balance']);
        $this->assertSame($job->driver_earnings, $data['payouts'][0]['amount']);
    }

    public function test_two_way_ratings_only_after_delivery_and_only_once(): void
    {
        $driver = $this->driverAt(1);
        $job = $this->paidJob();
        $buyer = $job->order->buyer;

        $this->actingAs($buyer)->postJson("/api/delivery/orders/{$job->order_id}/rating", ['rating' => 5])->assertStatus(409)->assertJsonPath('code', 'cannot_rate_yet');

        $job = $this->deliveredJobFor($driver, $buyer);
        // paidJob above created a second order for the same buyer; rate the delivered one.
        $this->actingAs($buyer)->postJson("/api/delivery/orders/{$job->order_id}/rating", ['rating' => 4, 'comment' => 'Haraka sana'])->assertCreated();
        $this->actingAs($buyer)->postJson("/api/delivery/orders/{$job->order_id}/rating", ['rating' => 1])->assertStatus(409)->assertJsonPath('code', 'already_rated');

        $this->assertSame(4.0, $driver->fresh()->rating_avg);
        $this->assertSame(1, $driver->fresh()->rating_count);

        $this->asDriver($driver)->postJson("/api/driver/jobs/{$job->id}/rating", ['rating' => 5])->assertCreated();
        $this->actingAs(User::factory()->create())->postJson("/api/delivery/orders/{$job->order_id}/rating", ['rating' => 1])->assertForbidden();

        $this->actingAs($buyer)->getJson("/api/delivery/orders/{$job->order_id}")->assertJsonPath('data.actions.can_rate', false);
    }

    public function test_delivered_order_unlocks_the_existing_seller_review(): void
    {
        $driver = $this->driverAt(1);
        $job = $this->deliveredJobFor($driver);

        $this->actingAs($job->order->buyer)->postJson("/api/orders/{$job->order_id}/review", ['rating' => 5, 'comment' => 'Bora'])
            ->assertSuccessful();
    }
}
