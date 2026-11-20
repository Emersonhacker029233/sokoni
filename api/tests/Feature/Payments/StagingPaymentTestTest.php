<?php

namespace Tests\Feature\Payments;

use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Tests\Feature\Returns\ReturnsTestHelpers;
use Tests\TestCase;

/**
 * The staging payment test behind tools/staging-dalipay-test.ps1: only on a
 * staging/local server with the token, only against Dalipay's test mode.
 */
class StagingPaymentTestTest extends TestCase
{
    use FakesDalipay;
    use RefreshDatabase;
    use ReturnsTestHelpers;

    private const TOKEN = 'stg_TOKEN_0123456789abcdefghij';

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpReturns();
        $this->setUpDalipay();
        config(['payments.staging_test_token' => self::TOKEN, 'delivery.enabled' => false]);
        Http::fake(['https://192.0.2.1/*' => Http::failedConnection('cURL error 28: Connection timed out')]);
    }

    private function start(array $body = [], string $token = self::TOKEN): TestResponse
    {
        return $this->postJson('/api/staging/payments', ['msisdn' => '+255754123456', 'channel' => 'mpesa', 'amount' => 1000, ...$body], ['X-Staging-Token' => $token]);
    }

    private function check(int $id): TestResponse
    {
        return $this->getJson("/api/staging/payments/{$id}", ['X-Staging-Token' => self::TOKEN]);
    }

    public function test_it_answers_404_in_production_without_the_token_or_with_a_wrong_one(): void
    {
        $this->start(token: 'wrong-token-0123456789abcdef')->assertNotFound();
        $this->postJson('/api/staging/payments', [])->assertNotFound();

        config(['payments.staging_test_token' => 'short']);
        $this->start(token: 'short')->assertNotFound();

        config(['payments.staging_test_token' => self::TOKEN]);
        $this->app['env'] = 'production';
        $this->start()->assertNotFound();
        $this->assertSame(0, Payment::query()->count());
        $this->assertSame([], $this->dalipaySent);
    }

    public function test_it_refuses_anything_but_dalipay_test_mode(): void
    {
        config(['payments.provider' => 'fake']);
        $this->start()->assertStatus(409)->assertJsonPath('code', 'not_dalipay_test');

        config(['payments.provider' => 'dalipay', 'payments.dalipay.env' => 'live', 'payments.dalipay.public_key' => 'gw_pk_production_a', 'payments.dalipay.secret_key' => 'gw_sk_production_b']);
        $this->start()->assertStatus(409);
        $this->assertSame(0, Payment::query()->count());
    }

    public function test_a_test_fee_is_pushed_through_dalipay_and_confirmed_with_bolt_switched_off(): void
    {
        $id = $this->start()->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.mode', 'normal')
            ->assertJsonPath('data.fee_paid', false)
            ->json('data.payment_id');
        $this->assertSame(1000, $this->dalipaySent[0]['body']['amount']);

        $this->dalipaySettles($this->lastDalipayUuid(), 'success');
        $this->check($id)->assertOk()
            ->assertJsonPath('data.status', 'succeeded')
            ->assertJsonPath('data.fee_paid', true)
            ->assertJsonPath('data.dalipay_uuid', $this->lastDalipayUuid());

        $payment = Payment::query()->findOrFail($id);
        $this->assertSame('awaiting_ready', $payment->deliveryJob->status, 'paid, and nothing booked on Bolt');
        $this->assertSame('STAGING PAYMENT TEST', $payment->order->address);
    }

    public function test_replaying_the_webhook_twice_processes_it_once(): void
    {
        $id = $this->start()->json('data.payment_id');
        $this->dalipaySettles($this->lastDalipayUuid(), 'success');
        $this->check($id);

        $this->postJson("/api/staging/payments/{$id}/replay-webhook", [], ['X-Staging-Token' => self::TOKEN])->assertOk()
            ->assertJsonPath('data.first.http', 200)
            ->assertJsonPath('data.first.received', true)
            ->assertJsonPath('data.second.duplicate', true)
            ->assertJsonCount(1, 'data.payment.webhooks');
    }

    public function test_no_answer_waits_then_expires_as_a_timeout(): void
    {
        $id = $this->start(['mode' => 'no_answer'])->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.dalipay_uuid', null)
            ->json('data.payment_id');

        $this->travel(60)->seconds();
        $this->check($id)->assertJsonPath('data.status', 'pending');
        $this->travel(61)->seconds();
        $this->check($id)->assertJsonPath('data.status', 'failed')->assertJsonPath('data.failure_reason', 'timeout');
    }

    public function test_only_its_own_test_payments_can_be_read(): void
    {
        $id = $this->start()->json('data.payment_id');
        Payment::query()->whereKey($id)->update(['meta' => json_encode(['external_id' => 'x'])]);

        $this->check($id)->assertNotFound();
        $this->check(999999)->assertNotFound();
    }
}
