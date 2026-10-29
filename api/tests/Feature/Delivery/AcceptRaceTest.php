<?php

namespace Tests\Feature\Delivery;

use App\Models\DeliveryJob;
use App\Models\DeliveryOffer;
use App\Models\User;
use App\Services\Delivery\DeliveryActionException;
use App\Services\Delivery\DispatchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Two drivers must never get the same job. */
class AcceptRaceTest extends TestCase
{
    use DeliveryTestHelpers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpDelivery();
    }

    public function test_first_driver_wins_and_the_second_gets_already_taken(): void
    {
        $a = $this->driverAt(1);
        $b = $this->driverAt(2);
        $job = $this->paidJob();

        $this->asDriver($a)->postJson("/api/driver/offers/{$job->id}/accept")
            ->assertOk()
            ->assertJsonPath('data.status', 'driver_assigned')
            ->assertJsonPath('data.customer.name', $job->order->buyer->name);

        $this->asDriver($b)->postJson("/api/driver/offers/{$job->id}/accept")
            ->assertStatus(409)
            ->assertJsonPath('code', 'already_taken');

        $job->refresh();
        $this->assertSame($a->id, $job->driver_profile_id);
        $this->assertSame('won', DeliveryOffer::query()->where('driver_profile_id', $a->id)->value('status'));
        $this->assertSame('lost', DeliveryOffer::query()->where('driver_profile_id', $b->id)->value('status'));

        // The job disappears from B's list, and B's phone was told silently.
        $this->asDriver($b)->getJson('/api/driver/offers')->assertOk()->assertJsonCount(0, 'data');
        $withdrawn = $this->pushes->ofType('delivery_offer_withdrawn')->firstWhere('user_id', $b->user_id);
        $this->assertNotNull($withdrawn);
        $this->assertTrue($withdrawn['silent']);
    }

    /**
     * Simulates the real race: both drivers read the job while it is still
     * open, and the other driver's UPDATE lands first. The loser's offer is
     * still 'offered', so only the conditional UPDATE can stop them.
     */
    public function test_conditional_update_rejects_a_driver_whose_view_is_stale(): void
    {
        $a = $this->driverAt(1);
        $b = $this->driverAt(2);
        $job = $this->paidJob();
        $staleCopy = DeliveryJob::query()->findOrFail($job->id);

        // A's claim commits "concurrently" (B's offer row is left untouched).
        DeliveryJob::query()->whereKey($job->id)->update([
            'driver_profile_id' => $a->id, 'status' => 'driver_assigned', 'driver_assigned_at' => now(),
        ]);
        $this->assertSame('offered', DeliveryOffer::query()->where('driver_profile_id', $b->id)->value('status'));

        try {
            app(DispatchService::class)->accept($staleCopy, $b);
            $this->fail('Expected already_taken');
        } catch (DeliveryActionException $e) {
            $this->assertSame('already_taken', $e->errorCode);
            $this->assertSame(409, $e->status);
        }

        $this->assertSame($a->id, $job->fresh()->driver_profile_id);
    }

    public function test_many_drivers_accepting_produce_exactly_one_winner(): void
    {
        $drivers = collect(range(1, 6))->map(fn ($i) => $this->driverAt(0.3 * $i));
        $job = $this->paidJob();
        $this->assertSame(6, $job->offers()->count());

        $statuses = $drivers->map(fn ($d) => $this->asDriver($d)->postJson("/api/driver/offers/{$job->id}/accept")->status());

        $this->assertSame(1, $statuses->filter(fn ($s) => $s === 200)->count());
        $this->assertSame(5, $statuses->filter(fn ($s) => $s === 409)->count());
        $this->assertSame(1, DeliveryOffer::query()->where('status', 'won')->count());
        $this->assertSame(1, $job->events()->where('to_status', 'driver_assigned')->count());
    }

    public function test_a_driver_who_was_not_offered_cannot_take_the_job(): void
    {
        $this->driverAt(1);
        $farAway = $this->driverAt(9);
        $job = $this->paidJob();

        $this->asDriver($farAway)->postJson("/api/driver/offers/{$job->id}/accept")
            ->assertStatus(403)->assertJsonPath('code', 'not_offered');
    }

    public function test_one_driver_cannot_hold_two_jobs(): void
    {
        $driver = $this->driverAt(1);
        $first = $this->paidJob();
        $second = $this->paidJob(User::factory()->create());
        // Force an offer on the second job too (dispatch itself would skip a busy driver).
        DeliveryOffer::query()->updateOrCreate(
            ['delivery_job_id' => $second->id, 'driver_profile_id' => $driver->id],
            ['wave' => 1, 'distance_km' => 1, 'status' => 'offered', 'offered_at' => now()],
        );

        $this->asDriver($driver)->postJson("/api/driver/offers/{$first->id}/accept")->assertOk();
        $this->asDriver($driver)->postJson("/api/driver/offers/{$second->id}/accept")
            ->assertStatus(409)->assertJsonPath('code', 'driver_busy');

        $this->assertNull($second->fresh()->driver_profile_id);
    }

    public function test_declined_offers_are_not_re_offered(): void
    {
        $driver = $this->driverAt(1);
        $job = $this->paidJob();

        $this->asDriver($driver)->postJson("/api/driver/offers/{$job->id}/decline")->assertOk();
        $this->artisan('delivery:dispatch-tick')->assertSuccessful();

        $this->assertSame('declined', $job->offers()->value('status'));
        $this->asDriver($driver)->getJson('/api/driver/offers')->assertJsonCount(0, 'data');
    }
}
