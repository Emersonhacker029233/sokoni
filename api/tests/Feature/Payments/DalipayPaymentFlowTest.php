<?php

namespace Tests\Feature\Payments;

use App\Filament\Resources\BoltDeliveries\Pages\ViewBoltDelivery;
use App\Filament\Resources\ReturnRequests\Pages\ViewReturnRequest;
use App\Models\DeliveryJob;
use App\Models\Payment;
use App\Models\PaymentWebhookEvent;
use App\Models\User;
use App\Services\Delivery\DeliveryJobService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;
use Tests\Feature\Returns\ReturnsTestHelpers;
use Tests\TestCase;

/**
 * "Pay now" with Dalipay, end to end through the real endpoints: the Bolt
 * delivery fee and the return ride fee, the signed webhook, the minute
 * reconcile, and the admin pages. Dalipay is FakesDalipay (Http::fake).
 */
class DalipayPaymentFlowTest extends TestCase
{
    use FakesDalipay;
    use RefreshDatabase;
    use ReturnsTestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpReturns();
        $this->setUpDalipay();
    }

    private function unpaidJob(): DeliveryJob
    {
        return $this->acceptAsSeller($this->placeDispatchOrder());
    }

    private function pay(DeliveryJob $job, string $channel = 'mpesa', ?string $key = null): TestResponse
    {
        return $this->actingAs($job->order->buyer)->postJson("/api/delivery/orders/{$job->order_id}/pay", [
            'channel' => $channel,
            'msisdn' => '+255754123456',
            'idempotency_key' => $key ?? 'k'.Str::random(23),
        ]);
    }

    private function poll(DeliveryJob $job): TestResponse
    {
        return $this->actingAs($job->order->buyer)->getJson("/api/delivery/orders/{$job->order_id}")->assertOk();
    }

    /** The payer approves (or declines) on the phone and Dalipay sends its signed webhook. */
    private function payerAnswers(string $uuid, string $status): TestResponse
    {
        return $this->postWebhook($this->webhookBody($this->dalipaySettles($uuid, $status)));
    }

    public function test_pay_now_sends_a_dalipay_push_and_the_signed_webhook_confirms_it(): void
    {
        $job = $this->unpaidJob();
        $key = 'k'.Str::random(23);

        $this->pay($job, key: $key)->assertStatus(202)
            ->assertJsonPath('data.payment.attempt.status', 'pending')
            ->assertJsonPath('data.payment.attempt.amount', $job->fee)
            ->assertJsonPath('data.payment.sandbox', true)
            ->assertJsonPath('data.payment.channels', ['mpesa', 'airtel', 'mixx', 'halopesa', 'azampesa']);

        $payment = Payment::query()->sole();
        $uuid = $this->lastDalipayUuid();
        $this->assertSame('dalipay', $payment->provider);
        $this->assertSame($uuid, $payment->provider_reference);
        $this->assertSame($key, $payment->meta['external_id']);
        $this->assertStringStartsWith('col_', $payment->meta['dalipay_reference']);
        $this->poll($job)->assertJsonPath('data.payment.attempt.status', 'pending')->assertJsonPath('data.payment_status', 'unpaid');

        $this->payerAnswers($uuid, 'success')->assertOk()->assertExactJson(['received' => true]);

        $job->refresh();
        $this->assertSame('paid', $job->payment_status);
        $this->assertSame('awaiting_ready', $job->status);
        $this->assertSame('succeeded', $payment->fresh()->status);
        $event = PaymentWebhookEvent::query()->sole();
        $this->assertSame("{$uuid}:collection.success", $event->event_id);
        $this->assertTrue($event->signature_valid);
        $this->assertSame('pending->succeeded', $event->result);
        $this->poll($job)->assertJsonPath('data.payment.receipt.amount', $job->fee)->assertJsonPath('data.payment.receipt.channel', 'mpesa');
    }

    public function test_a_declined_push_fails_and_the_buyer_can_pay_again(): void
    {
        $job = $this->unpaidJob();
        $this->pay($job)->assertStatus(202);

        $this->payerAnswers($this->lastDalipayUuid(), 'failed')->assertOk();

        $this->poll($job)
            ->assertJsonPath('data.payment.attempt.status', 'failed')
            ->assertJsonPath('data.payment.attempt.failure_reason', 'declined')
            ->assertJsonPath('data.actions.can_pay', true)
            ->assertJsonPath('data.payment_status', 'unpaid');
        $this->pay($job)->assertStatus(202)->assertJsonPath('data.payment.attempt.status', 'pending');
        $this->assertSame(2, Payment::query()->count());
    }

    public function test_an_unanswered_push_times_out_and_a_late_approval_with_a_lost_webhook_is_caught_by_the_cron(): void
    {
        $job = $this->unpaidJob();
        $this->pay($job)->assertStatus(202);
        $uuid = $this->lastDalipayUuid();

        $this->travel(121)->seconds();
        $this->poll($job)
            ->assertJsonPath('data.payment.attempt.status', 'failed')
            ->assertJsonPath('data.payment.attempt.failure_reason', 'timeout');

        // The payer approves after all; the webhook never arrives.
        $this->dalipaySettles($uuid, 'success');
        $this->artisan('payments:reconcile')->assertSuccessful();

        $payment = Payment::query()->sole();
        $this->assertSame('succeeded', $payment->status);
        $this->assertSame('timeout', $payment->meta['late_after']);
        $this->assertSame('paid', $job->fresh()->payment_status);
    }

    public function test_a_bad_signature_is_refused_never_stored_and_never_blocks_the_real_webhook(): void
    {
        $job = $this->unpaidJob();
        $this->pay($job)->assertStatus(202);
        $body = $this->webhookBody($this->dalipaySettles($this->lastDalipayUuid(), 'success'));
        $queries = $this->dalipayStatusQueries();

        $this->postWebhook($body, hash_hmac('sha256', $body, 'not-the-secret'))->assertStatus(401)->assertJsonPath('message', 'Invalid signature.');
        $this->postWebhook($body, 'not hex')->assertStatus(401);
        $this->postWebhook(str_replace('"success"', '"failed"', $body), hash_hmac('sha256', $body, self::DALIPAY_WEBHOOK_SECRET))->assertStatus(401);
        $this->call('POST', '/api/payments/webhook/dalipay', [], [], [], ['CONTENT_TYPE' => 'application/json'], $body)->assertStatus(401);

        $this->assertSame(0, PaymentWebhookEvent::query()->count());
        $this->assertSame($queries, $this->dalipayStatusQueries(), 'a refused webhook never reaches Dalipay or the payment');
        $this->assertSame('unpaid', $job->fresh()->payment_status);

        $this->postWebhook($body)->assertOk();
        $this->assertSame('paid', $job->fresh()->payment_status);
    }

    public function test_without_a_webhook_secret_every_webhook_is_refused(): void
    {
        config(['payments.dalipay.webhook_secret' => '']);
        $this->postWebhook('{"event":"collection.success","data":{"uuid":"x"}}', hash_hmac('sha256', '{"event":"collection.success","data":{"uuid":"x"}}', ''))
            ->assertStatus(401)->assertJsonPath('message', 'Webhook not configured.');
        $this->assertSame(0, PaymentWebhookEvent::query()->count());
    }

    public function test_a_duplicate_webhook_is_answered_and_processed_once(): void
    {
        $job = $this->unpaidJob();
        $this->pay($job)->assertStatus(202);
        $body = $this->webhookBody($this->dalipaySettles($this->lastDalipayUuid(), 'success'));

        $this->postWebhook($body)->assertOk();
        $queries = $this->dalipayStatusQueries();
        $this->postWebhook($body)->assertOk()->assertJsonPath('duplicate', true);

        $this->assertSame($queries, $this->dalipayStatusQueries());
        $this->assertSame(1, PaymentWebhookEvent::query()->count());
        $this->assertSame(1, $job->events()->where('to_status', 'awaiting_ready')->count());
    }

    public function test_a_signed_body_alone_never_marks_a_fee_paid(): void
    {
        $job = $this->unpaidJob();
        $this->pay($job)->assertStatus(202);
        $collection = $this->dalipayCollections[$this->lastDalipayUuid()];

        // A correctly signed "success" (a replay, or a leaked secret) while Dalipay itself says processing.
        $this->postWebhook($this->webhookBody([...$collection, 'status' => 'success']))->assertOk();

        $this->assertSame('pending', Payment::query()->sole()->status);
        $this->assertSame('unpaid', $job->fresh()->payment_status);
        $this->assertSame('unchanged:pending', PaymentWebhookEvent::query()->sole()->result);
    }

    public function test_a_late_webhook_after_the_unpaid_delivery_was_cancelled_keeps_the_money_for_a_refund(): void
    {
        $job = $this->unpaidJob();
        $this->pay($job)->assertStatus(202);
        $uuid = $this->lastDalipayUuid();

        $this->travel(21)->minutes();
        $this->artisan('delivery:tick')->assertSuccessful();
        $this->assertSame('cancelled', $job->fresh()->status);
        $this->assertSame('timeout', Payment::query()->sole()->failure_reason);

        $this->payerAnswers($uuid, 'success')->assertOk();

        $job->refresh();
        $this->assertSame('cancelled', $job->status, 'nothing is booked for a cancelled order');
        $this->assertSame('paid', $job->payment_status);
        $this->assertSame('pending', $job->refund_status);
        $this->assertSame((int) $job->fee, (int) $job->refund_amount);
        $this->assertSame('succeeded', Payment::query()->sole()->status);
    }

    public function test_a_webhook_for_an_unknown_collection_is_recorded_and_ignored(): void
    {
        $body = json_encode(['event' => 'collection.success', 'data' => ['reference' => 'col_x', 'uuid' => (string) Str::uuid(), 'status' => 'success', 'amount' => 1000, 'currency' => 'TZS', 'external_id' => 'NOT-OURS']]);

        $this->postWebhook($body)->assertOk();
        $this->postWebhook(json_encode(['event' => 'disbursement.success', 'data' => ['uuid' => 'd1']]))->assertOk();

        $this->assertSame(['unknown_payment', 'ignored_event'], PaymentWebhookEvent::query()->orderBy('id')->pluck('result')->all());
        $this->assertSame(0, $this->dalipayStatusQueries());
    }

    public function test_a_different_amount_is_never_applied(): void
    {
        $job = $this->unpaidJob();
        $this->pay($job)->assertStatus(202);
        $uuid = $this->lastDalipayUuid();
        $this->dalipayCollections[$uuid]['amount'] = 100;

        $this->payerAnswers($uuid, 'success')->assertOk();
        $this->assertSame('amount_mismatch', PaymentWebhookEvent::query()->sole()->result);

        $this->artisan('payments:reconcile')->assertSuccessful();
        $this->assertSame('unpaid', $job->fresh()->payment_status);
        $this->assertSame(100, Payment::query()->sole()->meta['amount_mismatch']);
    }

    public function test_a_lost_answer_to_the_push_is_matched_from_the_webhook_by_our_reference(): void
    {
        $job = $this->unpaidJob();
        $this->dalipayNextCreate = 'no_answer';

        $this->pay($job)->assertStatus(202)->assertJsonPath('data.payment.attempt.status', 'pending');
        $payment = Payment::query()->sole();
        $this->assertNull($payment->provider_reference);
        $this->pay($job)->assertStatus(409)->assertJsonPath('code', 'payment_in_progress');

        $this->payerAnswers($this->lastDalipayUuid(), 'success')->assertOk();

        $this->assertSame($this->lastDalipayUuid(), $payment->fresh()->provider_reference);
        $this->assertSame('succeeded', $payment->fresh()->status);
        $this->assertSame('paid', $job->fresh()->payment_status);
    }

    public function test_a_second_confirmed_payment_is_owed_back_never_lost(): void
    {
        $job = $this->unpaidJob();
        $this->pay($job)->assertStatus(202);
        $first = $this->lastDalipayUuid();
        $this->travel(121)->seconds();
        $this->poll($job)->assertJsonPath('data.payment.attempt.failure_reason', 'timeout');

        $this->pay($job)->assertStatus(202);
        $this->payerAnswers($this->lastDalipayUuid(), 'success')->assertOk();
        $this->assertSame('paid', $job->fresh()->payment_status);

        // The first prompt is approved late, too.
        $this->payerAnswers($first, 'success')->assertOk();

        $late = Payment::query()->where('provider_reference', $first)->sole();
        $this->assertSame('refund_pending', $late->status);
        $this->assertSame('fee_already_settled', $late->meta['refund_reason']);
        $this->assertSame(1, $job->events()->where('to_status', 'awaiting_ready')->count());
    }

    public function test_record_fee_paid_stays_the_manual_fallback_and_a_dalipay_payment_after_it_is_owed_back(): void
    {
        $job = $this->unpaidJob();
        $this->pay($job)->assertStatus(202);
        app(DeliveryJobService::class)->markFeePaid($job, 'manual:QK7H2X9ABC', User::factory()->admin()->create());
        $this->assertSame('paid', $job->fresh()->payment_status);

        $this->payerAnswers($this->lastDalipayUuid(), 'success')->assertOk();

        $this->assertSame('refund_pending', Payment::query()->sole()->status);
    }

    public function test_the_reconcile_cron_settles_payments_with_bolt_switched_off(): void
    {
        $job = $this->unpaidJob();
        $this->pay($job)->assertStatus(202);
        $this->dalipaySettles($this->lastDalipayUuid(), 'success');
        config(['delivery.enabled' => false, 'delivery.bolt.client_id' => null]);

        $this->artisan('delivery:tick')->assertSuccessful(); // does nothing with Bolt off
        $this->assertSame('unpaid', $job->fresh()->payment_status);

        $this->artisan('payments:reconcile')->assertSuccessful();
        $this->assertSame('paid', $job->fresh()->payment_status);
    }

    public function test_the_reconcile_cron_does_nothing_without_dalipay(): void
    {
        config(['payments.provider' => 'none', 'payments.dalipay.public_key' => null, 'payments.dalipay.secret_key' => null]);

        $this->artisan('payments:reconcile')->assertSuccessful();
        $this->assertSame([], $this->dalipaySent);
    }

    public function test_azampesa_goes_to_dalipay_and_is_refused_where_the_gateway_does_not_offer_it(): void
    {
        $job = $this->unpaidJob();
        $this->pay($job, 'azampesa')->assertStatus(202);
        $this->assertSame('Azampesa', $this->dalipaySent[0]['body']['provider']);

        config(['payments.provider' => 'fake']);
        $other = $this->unpaidJob();
        $this->pay($other, 'azampesa')->assertStatus(422)->assertJsonValidationErrors('channel');
    }

    public function test_the_return_ride_fee_is_collected_with_dalipay_too(): void
    {
        $return = $this->requestedReturn('changed_mind');
        $this->asSeller($return)->postJson("/api/returns/{$return->id}/accept")->assertOk();
        $this->asBuyer($return)->postJson("/api/returns/{$return->id}/pay", [
            'channel' => 'airtel', 'msisdn' => '+255684123456', 'idempotency_key' => 'r'.Str::random(23),
        ])->assertStatus(202)->assertJsonPath('data.payment.attempt.status', 'pending');
        $this->assertSame(['Airtel', '0684123456'], [$this->dalipaySent[0]['body']['provider'], $this->dalipaySent[0]['body']['account_number']]);

        $this->payerAnswers($this->lastDalipayUuid(), 'success')->assertOk();

        $this->assertSame('paid', $return->fresh()->fee_status);
        $this->assertSame('succeeded', Payment::query()->where('purpose', 'return_fee')->sole()->status);
    }

    public function test_admins_see_the_dalipay_reference_status_and_webhooks_and_record_a_refund(): void
    {
        $job = $this->unpaidJob();
        $this->pay($job)->assertStatus(202);
        $first = $this->lastDalipayUuid();
        $this->travel(121)->seconds();
        $this->poll($job);
        $this->pay($job)->assertStatus(202);
        $this->payerAnswers($this->lastDalipayUuid(), 'success')->assertOk();
        $this->payerAnswers($first, 'success')->assertOk();
        $late = Payment::query()->where('provider_reference', $first)->sole();

        $admin = User::factory()->admin()->create();
        $this->actingAsAdmin($admin)->get("/admin/bolt-deliveries/{$job->id}")
            ->assertOk()
            ->assertSee('Payments (mobile money)')
            ->assertSee($first)
            ->assertSee($late->meta['dalipay_reference'])
            ->assertSee('collection.success')
            ->assertSee('REFUND OWED')
            ->assertDontSee('+255754123456');

        $this->actingAsAdmin($admin);
        Livewire::test(ViewBoltDelivery::class, ['record' => $job->id])
            ->callAction('markPaymentRefunded', data: ['payment_id' => $late->id, 'reference' => 'MPESA-RF-1'])
            ->assertHasNoActionErrors();

        $late->refresh();
        $this->assertSame('refunded', $late->status);
        $this->assertSame('MPESA-RF-1', $late->meta['refund_reference']);
        $this->assertNotNull($late->refunded_at);
    }

    public function test_the_returns_admin_page_shows_the_ride_fee_payment(): void
    {
        $return = $this->requestedReturn('changed_mind');
        $this->asSeller($return)->postJson("/api/returns/{$return->id}/accept")->assertOk();
        $this->asBuyer($return)->postJson("/api/returns/{$return->id}/pay", [
            'channel' => 'mpesa', 'msisdn' => '+255754123456', 'idempotency_key' => 'r'.Str::random(23),
        ])->assertStatus(202);
        $this->payerAnswers($this->lastDalipayUuid(), 'success')->assertOk();

        $this->actingAsAdmin(User::factory()->admin()->create());
        Livewire::test(ViewReturnRequest::class, ['record' => $return->id])
            ->assertSee($this->lastDalipayUuid())
            ->assertSee('SUCCEEDED')
            ->assertSee('collection.success');
    }
}
