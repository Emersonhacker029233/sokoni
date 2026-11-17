<?php

namespace Tests\Feature\Delivery;

use App\Models\DeliveryJob;
use App\Models\Payment;
use App\Models\User;
use App\Services\Delivery\DeliveryJobService;
use App\Services\Payment\FakePaymentGateway;
use App\Services\Payment\PaymentGateway;
use App\Services\Payment\UnavailablePaymentGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Sokoni 1.1 "Pay now" for the Bolt delivery fee, with the sandbox gateway. The app can start a
 * payment but never finish one: only the server, asking the gateway,
 * marks the fee paid.
 */
class CustomerPaymentTest extends TestCase
{
    use DeliveryTestHelpers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpDelivery();
        config([
            'payments.provider' => 'fake',
            'payments.fake.approve_after_seconds' => 8,
            'payments.attempt_timeout_seconds' => 120,
            'payments.status_query_interval_seconds' => 1,
        ]);
    }

    private function unpaidJob(): DeliveryJob
    {
        return $this->acceptAsSeller($this->placeDispatchOrder());
    }

    private function pay(DeliveryJob $job, string $msisdn = '+255754123456', ?string $key = null): TestResponse
    {
        return $this->actingAs($job->order->buyer)->postJson("/api/delivery/orders/{$job->order_id}/pay", [
            'channel' => 'mpesa',
            'msisdn' => $msisdn,
            'idempotency_key' => $key ?? (string) Str::uuid(),
        ]);
    }

    private function poll(DeliveryJob $job): TestResponse
    {
        return $this->actingAs($job->order->buyer)->getJson("/api/delivery/orders/{$job->order_id}")->assertOk();
    }

    public function test_pay_sends_a_prompt_and_only_the_server_confirmation_marks_the_fee_paid(): void
    {
        $job = $this->unpaidJob();
        $this->assertSame('pending_payment', $job->status);

        $response = $this->pay($job)->assertStatus(202);
        $response->assertJsonPath('data.status', 'pending_payment')
            ->assertJsonPath('data.payment_status', 'unpaid')
            ->assertJsonPath('data.payment.attempt.status', 'pending')
            ->assertJsonPath('data.payment.attempt.amount', $job->fee)
            ->assertJsonPath('data.payment.attempt.msisdn', '+25575••••456')
            ->assertJsonPath('data.payment.sandbox', true);

        // The payer hasn't approved yet: polling leaves it pending.
        $this->travel(5)->seconds();
        $this->poll($job)->assertJsonPath('data.payment.attempt.status', 'pending')->assertJsonPath('data.status', 'pending_payment');

        // Approved on the phone: the next poll asks the gateway and the job moves on.
        $this->travel(5)->seconds();
        $this->poll($job)
            ->assertJsonPath('data.status', 'awaiting_ready')
            ->assertJsonPath('data.payment_status', 'paid')
            ->assertJsonPath('data.amount_due', 0)
            ->assertJsonPath('data.payment.receipt.amount', $job->fee)
            ->assertJsonPath('data.actions.can_pay', false);

        $payment = Payment::query()->sole();
        $this->assertSame('succeeded', $payment->status);
        $this->assertSame('fake', $payment->provider);
        $this->assertSame('delivery_fee', $payment->purpose);
        $this->assertSame((int) $job->fee, (int) $payment->amount);
        $this->assertNotNull($job->fresh()->paid_at);

        // The seller hears the fee was paid: pack it and mark it ready.
        $seller = $job->order->seller->user;
        $this->assertTrue($this->pushes->to($seller)->contains(
            fn ($p) => $p['body'] === __('delivery.push.seller_fee_paid.body', ['code' => $job->order->code], $seller->preferredLocale())
        ));
    }

    public function test_the_amount_comes_from_the_job_not_the_request(): void
    {
        $job = $this->unpaidJob();

        $this->actingAs($job->order->buyer)->postJson("/api/delivery/orders/{$job->order_id}/pay", [
            'channel' => 'mpesa', 'msisdn' => '+255754123456', 'idempotency_key' => 'key-amount-test', 'amount' => 1,
        ])->assertStatus(202);

        $this->assertSame((int) $job->fee, (int) Payment::query()->sole()->amount);
    }

    public function test_the_same_idempotency_key_never_sends_a_second_prompt(): void
    {
        $job = $this->unpaidJob();

        $this->pay($job, key: 'tap-once-123')->assertStatus(202);
        $this->pay($job, key: 'tap-once-123')->assertStatus(202);

        $this->assertSame(1, Payment::query()->count());
    }

    public function test_a_second_prompt_waits_until_the_first_one_has_expired(): void
    {
        $job = $this->unpaidJob();

        $this->pay($job, '+255754120002')->assertStatus(202); // pretend payer never answers
        $this->pay($job)->assertStatus(409)->assertJsonPath('code', 'payment_in_progress');

        $this->travel(121)->seconds();
        $this->pay($job)->assertStatus(202)->assertJsonPath('data.payment.attempt.status', 'pending');

        $this->assertSame(['failed', 'pending'], Payment::query()->orderBy('id')->pluck('status')->all());
        $this->assertSame('timeout', Payment::query()->orderBy('id')->first()->failure_reason);
    }

    public function test_a_declined_payment_is_failed_the_buyer_is_told_and_can_try_again(): void
    {
        $job = $this->unpaidJob();

        $this->pay($job, '+255754120001')->assertStatus(202);
        $this->travel(9)->seconds();
        $this->poll($job)
            ->assertJsonPath('data.status', 'pending_payment')
            ->assertJsonPath('data.payment.attempt.status', 'failed')
            ->assertJsonPath('data.payment.attempt.failure_reason', 'declined')
            ->assertJsonPath('data.actions.can_pay', true);

        $buyer = $job->order->buyer;
        $this->assertTrue($this->pushes->to($buyer)->contains(
            fn ($p) => $p['title'] === __('delivery.push.buyer_payment_failed.title', [], $buyer->preferredLocale())
        ));

        $this->pay($job)->assertStatus(202);
        $this->assertSame(2, Payment::query()->count());
    }

    public function test_an_unanswered_prompt_times_out_from_the_minute_cron(): void
    {
        $job = $this->unpaidJob();
        $this->pay($job, '+255754120002')->assertStatus(202);

        $this->travel(121)->seconds();
        $this->artisan('delivery:tick')->expectsOutput('settled: 1')->assertSuccessful();

        $payment = Payment::query()->sole();
        $this->assertSame('failed', $payment->status);
        $this->assertSame('timeout', $payment->failure_reason);
        $this->assertSame('pending_payment', $job->fresh()->status);
    }

    public function test_the_cron_confirms_a_payment_even_when_nobody_is_polling(): void
    {
        $job = $this->unpaidJob();
        $this->pay($job)->assertStatus(202);

        $this->travel(10)->seconds();
        $this->artisan('delivery:tick')->assertSuccessful();

        $this->assertSame('paid', $job->fresh()->payment_status);
        $this->assertSame('awaiting_ready', $job->fresh()->status);
    }

    public function test_a_payment_confirmed_after_the_twenty_minute_cancel_is_kept_for_a_full_refund(): void
    {
        $job = $this->unpaidJob();
        $this->travel(19)->minutes();
        $this->pay($job)->assertStatus(202);

        // The unpaid timeout fires before anyone asks the gateway again.
        $this->travel(61)->seconds();
        app(DeliveryJobService::class)->expireUnpaidJobs();
        $this->assertSame('cancelled', $job->fresh()->status);

        $this->artisan('delivery:tick')->assertSuccessful();

        $job->refresh();
        $this->assertSame('cancelled', $job->status);
        $this->assertSame('paid', $job->payment_status);
        $this->assertSame((int) $job->fee, (int) $job->refund_amount);
        $this->assertSame('pending', $job->refund_status);
        $this->poll($job)->assertJsonPath('data.refund.amount', $job->fee)->assertJsonPath('data.refund.status', 'pending');
    }

    public function test_only_the_buyer_can_pay_and_only_while_the_fee_is_owed(): void
    {
        $job = $this->unpaidJob();
        $body = ['channel' => 'mpesa', 'msisdn' => '+255754123456', 'idempotency_key' => 'someone-else-1'];

        $this->actingAs($job->order->seller->user)->postJson("/api/delivery/orders/{$job->order_id}/pay", $body)->assertForbidden();
        $this->actingAs(User::factory()->create())->postJson("/api/delivery/orders/{$job->order_id}/pay", $body)->assertForbidden();

        $paid = $this->paidJob(User::factory()->create(['phone' => '+255711000444']));
        $this->actingAs($paid->order->buyer)->postJson("/api/delivery/orders/{$paid->order_id}/pay", [...$body, 'idempotency_key' => 'already-paid-1'])
            ->assertStatus(409)->assertJsonPath('code', 'not_payable');
    }

    public function test_a_key_used_for_one_order_cannot_be_replayed_on_another(): void
    {
        $buyer = User::factory()->create(['phone' => '+255711000222']);
        $first = $this->acceptAsSeller($this->placeDispatchOrder($buyer));
        $second = $this->acceptAsSeller($this->placeDispatchOrder($buyer));

        $this->pay($first, key: 'shared-key-1')->assertStatus(202);
        $this->pay($second, key: 'shared-key-1')->assertStatus(409)->assertJsonPath('code', 'idempotency_key_reused');
    }

    public function test_pay_validates_channel_and_number(): void
    {
        $job = $this->unpaidJob();

        $this->actingAs($job->order->buyer)->postJson("/api/delivery/orders/{$job->order_id}/pay", [
            'channel' => 'paypal', 'msisdn' => '0754123456', 'idempotency_key' => 'x',
        ])->assertUnprocessable()->assertJsonValidationErrors(['channel', 'msisdn', 'idempotency_key']);
    }

    public function test_without_a_payment_provider_pay_now_is_not_offered(): void
    {
        config(['payments.provider' => 'none']);
        $job = $this->unpaidJob();

        $this->poll($job)
            ->assertJsonPath('data.actions.can_pay', false)
            ->assertJsonPath('data.payment.online_available', false)
            ->assertJsonPath('data.amount_due', $job->fee);
        $this->pay($job)->assertStatus(503)->assertJsonPath('code', 'payments_unavailable');
    }

    public function test_the_sandbox_gateway_is_never_used_in_production(): void
    {
        config(['payments.provider' => 'fake']);
        $this->app->detectEnvironment(fn () => 'production');
        $this->assertInstanceOf(UnavailablePaymentGateway::class, $this->app->make(PaymentGateway::class));

        $this->app->detectEnvironment(fn () => 'staging');
        $this->assertInstanceOf(FakePaymentGateway::class, $this->app->make(PaymentGateway::class));
    }
}
