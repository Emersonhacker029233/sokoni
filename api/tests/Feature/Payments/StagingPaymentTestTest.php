<?php

namespace Tests\Feature\Payments;

use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
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

    public function test_it_refuses_without_dalipay_or_with_keys_that_are_refused(): void
    {
        config(['payments.provider' => 'fake']);
        $this->start()->assertStatus(409)->assertJsonPath('code', 'not_dalipay');

        config(['payments.provider' => 'dalipay', 'payments.dalipay.public_key' => 'gw_pk_production_a']); // mixed test/production keys
        $this->start()->assertStatus(409)->assertJsonPath('code', 'not_dalipay');
        $this->assertSame(0, Payment::query()->count());
        $this->assertSame([], $this->dalipaySent);
    }

    /** Live keys on staging, every condition met: the only way a real collection leaves staging. */
    private function liveOnStaging(array $config = []): void
    {
        config([
            'payments.dalipay.env' => 'live',
            'payments.dalipay.public_key' => 'gw_pk_production_LIVEpub',
            'payments.dalipay.secret_key' => 'gw_sk_production_LIVEsec',
            'payments.staging_allow_live' => true,
            'payments.staging_live_msisdn' => '+255754123456',
            ...$config,
        ]);
        $this->app['env'] = 'staging';
    }

    public function test_a_live_collection_from_staging_needs_every_condition_and_is_logged_as_a_warning(): void
    {
        $this->liveOnStaging();
        $logged = [];
        Log::listen(function ($message) use (&$logged) {
            $logged[] = [$message->level, $message->message];
        });

        $this->postJson('/api/staging/payments', ['msisdn' => '+255754123456', 'live' => true], ['X-Staging-Token' => self::TOKEN])
            ->assertCreated()
            ->assertJsonPath('data.live', true)
            ->assertJsonPath('data.amount', 1000)
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.channel', null);

        $this->assertSame(1000, $this->dalipaySent[0]['body']['amount']);
        $this->assertSame(['gw_sk_production_LIVEsec'], $this->dalipaySent[0]['headers']['X-Secret-Key'], 'sent with the live keys');
        $warnings = array_values(array_filter($logged, fn ($l) => $l[0] === 'warning' && str_contains($l[1], 'STAGING LIVE COLLECTION')));
        $this->assertCount(1, $warnings);
        $this->assertStringContainsString('(real money): TSh 1000 to the staging live number (ending 456)', $warnings[0][1]);
        $this->assertStringNotContainsString('+255754123456', implode("\n", array_column($logged, 1)));
        $this->assertStringNotContainsString('gw_sk_production_LIVEsec', implode("\n", array_column($logged, 1)));

        // The real result is confirmed exactly as in test mode.
        $id = Payment::query()->sole()->id;
        $this->dalipaySettles($this->lastDalipayUuid(), 'success');
        $this->check($id)->assertJsonPath('data.status', 'succeeded')->assertJsonPath('data.fee_paid', true);
        $this->postJson("/api/staging/payments/{$id}/replay-webhook", [], ['X-Staging-Token' => self::TOKEN])
            ->assertOk()->assertJsonPath('data.second.duplicate', true);
    }

    /** @return array<string, array{array<string, mixed>, array<string, mixed>, string, string}> */
    public static function liveRefusals(): array
    {
        return [
            'APP_ENV local' => [[], [], 'local', 'APP_ENV=staging'],
            'STAGING_PAYMENTS_ALLOW_LIVE not set' => [['payments.staging_allow_live' => false], [], 'staging', 'STAGING_PAYMENTS_ALLOW_LIVE=true'],
            'no live: true in the request' => [[], ['live' => false], 'staging', 'live: true'],
            'the timeout test' => [[], ['mode' => 'no_answer'], 'staging', 'no_answer is for test mode'],
            'more than TSh 2,000' => [[], ['amount' => 2001], 'staging', 'at most TSh 2,000'],
            'another number' => [[], ['msisdn' => '+255754999999'], 'staging', 'only go to STAGING_PAYMENTS_LIVE_MSISDN'],
            'no live number configured' => [['payments.staging_live_msisdn' => null], [], 'staging', 'Set STAGING_PAYMENTS_LIVE_MSISDN'],
            'a live number that is not E.164' => [['payments.staging_live_msisdn' => '0754123456'], ['msisdn' => '+255754123456'], 'staging', 'Set STAGING_PAYMENTS_LIVE_MSISDN'],
        ];
    }

    #[DataProvider('liveRefusals')]
    public function test_a_live_collection_is_refused_when_any_condition_is_missing(array $config, array $body, string $env, string $reason): void
    {
        $this->liveOnStaging($config);
        $this->app['env'] = $env;

        $response = $this->postJson('/api/staging/payments', ['msisdn' => '+255754123456', 'amount' => 1000, 'live' => true, ...$body], ['X-Staging-Token' => self::TOKEN])
            ->assertStatus(409)
            ->assertJsonPath('code', 'live_not_allowed');
        $this->assertStringContainsString($reason, (string) $response->json('message'));
        $this->assertStringNotContainsString('+255754123456', (string) $response->json('message'), 'the allowed number is never echoed');

        $this->assertSame([], $this->dalipaySent, 'nothing reached Dalipay');
        $this->assertSame(0, Payment::query()->count());
    }

    public function test_production_answers_404_even_with_every_live_setting(): void
    {
        $this->liveOnStaging();
        $this->app['env'] = 'production';

        $this->postJson('/api/staging/payments', ['msisdn' => '+255754123456', 'live' => true], ['X-Staging-Token' => self::TOKEN])->assertNotFound();
        $this->assertSame([], $this->dalipaySent);
    }

    public function test_asking_for_live_against_test_keys_is_refused(): void
    {
        $this->start(['live' => true])->assertStatus(409)->assertJsonPath('code', 'not_live');
        $this->assertSame([], $this->dalipaySent);
    }

    public function test_test_mode_takes_no_network_and_defaults_to_tsh_1000(): void
    {
        $this->postJson('/api/staging/payments', ['msisdn' => '+255684123456'], ['X-Staging-Token' => self::TOKEN])
            ->assertCreated()
            ->assertJsonPath('data.amount', 1000)
            ->assertJsonPath('data.live', false)
            ->assertJsonPath('data.dalipay_provider', 'Airtel');
        $this->assertArrayNotHasKey('provider', $this->dalipaySent[0]['body']);
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
