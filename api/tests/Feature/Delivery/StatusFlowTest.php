<?php

namespace Tests\Feature\Delivery;

use App\Models\DeliveryJob;
use App\Models\DriverProfile;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StatusFlowTest extends TestCase
{
    use DeliveryTestHelpers;
    use RefreshDatabase;

    private DriverProfile $driver;

    private DeliveryJob $job;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpDelivery();
        $this->driver = $this->driverAt(1);
        $this->job = $this->paidJob();
        $this->asDriver($this->driver)->postJson("/api/driver/offers/{$this->job->id}/accept")->assertOk();
    }

    private function move(string $status, array $extra = [])
    {
        return $this->asDriver($this->driver)->postJson("/api/driver/jobs/{$this->job->id}/status", ['status' => $status, ...$extra]);
    }

    public function test_happy_path_with_pickup_code_and_pin_completes_the_order(): void
    {
        $this->move('arrived_at_shop')->assertOk()->assertJsonPath('data.status', 'arrived_at_shop');

        $this->move('picked_up', ['pickup_code' => 'ZZZZ'])->assertStatus(422)->assertJsonPath('code', 'wrong_pickup_code');
        $this->move('picked_up', ['pickup_code' => strtolower($this->job->pickupCode())])->assertOk();

        $this->move('on_the_way')->assertOk();
        $this->move('arrived')->assertOk();

        $wrongPin = $this->job->fresh()->delivery_pin === '0000' ? '1111' : '0000';
        $this->move('delivered', ['pin' => $wrongPin])->assertStatus(422)
            ->assertJsonPath('code', 'wrong_pin')->assertJsonPath('attempts_left', 4);

        $this->move('delivered', ['pin' => $this->job->fresh()->delivery_pin])->assertOk()->assertJsonPath('data.status', 'delivered');

        $job = $this->job->fresh();
        $order = $job->order;
        $this->assertSame('completed', $order->status);
        $this->assertSame('paid', $order->payment_status);
        $this->assertNotNull($order->completed_at);
        $this->assertSame($job->driver_earnings, $job->earned_amount);
        $this->assertSame(1, $this->driver->fresh()->jobs_completed);

        $buyerStatuses = $this->pushes->to($order->buyer)->pluck('data.status')->all();
        foreach (['driver_assigned', 'arrived_at_shop', 'picked_up', 'on_the_way', 'arrived', 'delivered'] as $s) {
            $this->assertContains($s, $buyerStatuses, "Buyer should hear about {$s}");
        }
        $this->assertSame(
            ['pending_payment', 'pending_driver', 'driver_assigned', 'arrived_at_shop', 'picked_up', 'on_the_way', 'arrived', 'delivered'],
            $job->events()->where('type', 'status')->pluck('to_status')->all(),
        );
    }

    public function test_skipping_steps_is_rejected(): void
    {
        $this->move('picked_up', ['pickup_code' => $this->job->pickupCode()])->assertStatus(409)->assertJsonPath('code', 'invalid_transition');
        $this->move('delivered', ['pin' => $this->job->fresh()->delivery_pin])->assertStatus(409);
        $this->assertSame('driver_assigned', $this->job->fresh()->status);
    }

    public function test_pin_locks_after_five_wrong_attempts(): void
    {
        $this->driveToArrived($this->job, $this->driver);
        $wrong = $this->job->fresh()->delivery_pin === '0000' ? '1111' : '0000';

        foreach (range(1, 4) as $i) {
            $this->move('delivered', ['pin' => $wrong])->assertStatus(422);
        }
        $this->move('delivered', ['pin' => $wrong])->assertStatus(423)->assertJsonPath('code', 'pin_locked');
        // Even the right PIN is refused once locked.
        $this->move('delivered', ['pin' => $this->job->fresh()->delivery_pin])->assertStatus(423);

        $job = $this->job->fresh();
        $this->assertNotNull($job->pin_locked_at);
        $this->assertSame('arrived', $job->status);
        $this->assertTrue($this->pushes->to($job->order->buyer)->contains(fn ($p) => str_contains($p['title'], 'PIN')));
    }

    public function test_proof_photo_is_required_only_when_the_setting_is_on(): void
    {
        Storage::fake('local');
        Settings::set('delivery_proof_photo_required', '1');
        $this->driveToArrived($this->job, $this->driver);
        $pin = $this->job->fresh()->delivery_pin;

        $this->move('delivered', ['pin' => $pin])->assertStatus(422)->assertJsonPath('code', 'proof_photo_required');

        $this->asDriver($this->driver)->post("/api/driver/jobs/{$this->job->id}/status", [
            'status' => 'delivered', 'pin' => $pin, 'proof_photo' => UploadedFile::fake()->image('proof.jpg'),
        ], ['Accept' => 'application/json'])->assertOk();

        $path = $this->job->fresh()->proof_photo;
        $this->assertNotNull($path);
        Storage::disk('local')->assertExists($path);
    }

    public function test_customer_unreachable_needs_waiting_time_and_call_attempts(): void
    {
        $this->driveToArrived($this->job, $this->driver);

        $this->move('failed', ['reason' => 'customer_unreachable'])->assertStatus(409)->assertJsonPath('code', 'unreachable_too_early');

        $this->travel(11)->minutes();
        $this->move('failed', ['reason' => 'customer_unreachable'])->assertStatus(409)->assertJsonPath('code', 'unreachable_needs_calls');

        $this->asDriver($this->driver)->postJson("/api/driver/jobs/{$this->job->id}/call-attempts")->assertOk();
        $this->asDriver($this->driver)->postJson("/api/driver/jobs/{$this->job->id}/call-attempts")->assertOk()->assertJsonPath('data.call_attempts', 2);

        $this->move('failed', ['reason' => 'customer_unreachable', 'note' => 'Hakuna jibu'])->assertOk();

        $job = $this->job->fresh();
        $this->assertSame('failed', $job->status);
        $this->assertSame($job->driver_earnings, $job->earned_amount, 'Driver keeps earnings when the customer is unreachable.');
        $this->assertNull($job->refund_amount);
        $this->assertSame('cancelled', $job->order->status);
    }

    public function test_driver_side_failure_earns_nothing_and_buyer_may_retry(): void
    {
        $this->driveToArrived($this->job, $this->driver);
        $this->move('failed', ['reason' => 'vehicle_breakdown'])->assertOk();

        $job = $this->job->fresh();
        $this->assertSame(0, $job->earned_amount);
        $this->assertSame('accepted', $job->order->status, 'Order stays open so the buyer can retry.');

        $this->actingAs($job->order->buyer)->postJson("/api/delivery/orders/{$job->order_id}/retry")
            ->assertOk()->assertJsonPath('data.status', 'pending_driver');
        $this->assertNull($job->fresh()->driver_profile_id);
    }

    public function test_driver_cancel_before_pickup_redispatches_without_that_driver(): void
    {
        $other = $this->driverAt(2);
        // $other was offered in wave 1 too and lost the race; it should be re-offered.
        $this->move('cancelled', ['note' => 'Pikipiki imeharibika'])->assertOk();

        $job = $this->job->fresh();
        $this->assertSame('pending_driver', $job->status);
        $this->assertNull($job->driver_profile_id);
        $this->assertSame(1, $this->driver->fresh()->cancellations_count);
        $this->assertSame('cancelled', $job->offers()->where('driver_profile_id', $this->driver->id)->value('status'));
        $this->assertSame('offered', $job->offers()->where('driver_profile_id', $other->id)->value('status'));
        $this->assertTrue($this->pushes->to($job->order->buyer)->contains(fn ($p) => $p['data']['status'] === 'pending_driver'));
    }

    public function test_repeated_driver_cancellations_flag_the_driver(): void
    {
        foreach (range(1, 3) as $i) {
            if ($i > 1) {
                $this->job = $this->paidJob(\App\Models\User::factory()->create());
                $this->asDriver($this->driver)->postJson("/api/driver/offers/{$this->job->id}/accept")->assertOk();
            }
            $this->move('cancelled')->assertOk();
        }

        $this->assertNotNull($this->driver->fresh()->flagged_at);
    }

    public function test_driver_cannot_cancel_after_pickup(): void
    {
        $this->move('arrived_at_shop')->assertOk();
        $this->move('picked_up', ['pickup_code' => $this->job->pickupCode()])->assertOk();

        $this->move('cancelled')->assertStatus(409)->assertJsonPath('code', 'cannot_cancel_in_transit');
    }

    public function test_buyer_cannot_cancel_the_order_once_goods_left_the_shop(): void
    {
        $this->move('arrived_at_shop')->assertOk();
        $this->move('picked_up', ['pickup_code' => $this->job->pickupCode()])->assertOk();
        $order = $this->job->order;

        // Existing Sokoni 1.0 endpoint.
        $this->actingAs($order->buyer)->patchJson("/api/orders/{$order->id}/status", ['status' => 'cancelled', 'reason' => 'x'])->assertStatus(422);
        // New delivery endpoint.
        $this->actingAs($order->buyer)->postJson("/api/delivery/orders/{$order->id}/cancel")->assertStatus(409);

        $this->assertSame('picked_up', $this->job->fresh()->status);
    }
}
