<?php

namespace Tests\Feature\Payments;

use App\Services\Payment\CollectionRequest;
use App\Services\Payment\DalipayGateway;
use App\Services\Payment\PaymentGateway;
use App\Services\Payment\PaymentGatewayFactory;
use App\Services\Payment\UnavailablePaymentGateway;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** The Dalipay adapter on its own: requests, answers, and which credentials are refused. */
class DalipayGatewayTest extends TestCase
{
    use FakesDalipay;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpDalipay();
    }

    private function gateway(): DalipayGateway
    {
        $gateway = app(PaymentGateway::class);
        $this->assertInstanceOf(DalipayGateway::class, $gateway);

        return $gateway;
    }

    private function request(string $channel = 'mpesa', string $reference = 'k7Hq2LmN9pQ4rS6tU8vW0xY1'): CollectionRequest
    {
        return new CollectionRequest($reference, 6400, 'TZS', '+255754123456', $channel, 'Sokoni delivery SKAB12CD34');
    }

    public function test_a_collection_is_a_push_to_the_local_number_with_both_keys_and_our_reference(): void
    {
        $result = $this->gateway()->initiateCollection($this->request('mixx'));

        $this->assertTrue($result->accepted());
        $this->assertSame($this->lastDalipayUuid(), $result->providerReference);
        $sent = $this->dalipaySent[0];
        $this->assertSame(['POST', '/api/v1/collections'], [$sent['method'], $sent['path']]);
        $this->assertSame([self::DALIPAY_PUBLIC], $sent['headers']['X-Public-Key']);
        $this->assertSame([self::DALIPAY_SECRET], $sent['headers']['X-Secret-Key']);
        $this->assertSame([
            'account_number' => '0754123456',
            'amount' => 6400,
            'currency' => 'TZS',
            'provider' => 'Tigo', // Mixx by Yas is Dalipay's "Tigo"
            'external_id' => 'k7Hq2LmN9pQ4rS6tU8vW0xY1',
        ], $sent['body']);
        $this->assertArrayNotHasKey('customer_name', $sent['body'], 'no personal data Dalipay does not need');
        $this->assertStringStartsWith('col_', $result->meta['dalipay_reference']);
    }

    public function test_every_sokoni_channel_maps_to_a_dalipay_provider_and_azampesa_is_offered(): void
    {
        $this->assertSame(['mpesa', 'airtel', 'mixx', 'halopesa', 'azampesa'], $this->gateway()->capabilities()->channels);
        $this->assertTrue($this->gateway()->capabilities()->sandbox);
        foreach (['mpesa' => 'Mpesa', 'airtel' => 'Airtel', 'halopesa' => 'Halopesa', 'azampesa' => 'Azampesa'] as $channel => $provider) {
            $this->gateway()->initiateCollection($this->request($channel, 'key-'.$channel.'-123456'));
            $this->assertSame($provider, end($this->dalipaySent)['body']['provider']);
        }
        $this->assertSame('unsupported_channel', $this->gateway()->initiateCollection($this->request('cash'))->failureReason);
    }

    public function test_a_long_idempotency_key_is_hashed_into_a_30_character_external_id(): void
    {
        $key = '3f2b8c1e-9a4d-4e7f-b6c5-1d2e3f4a5b6c';
        $this->gateway()->initiateCollection($this->request(reference: $key));

        $externalId = $this->dalipaySent[0]['body']['external_id'];
        $this->assertSame(30, strlen($externalId));
        $this->assertSame(DalipayGateway::externalId($key), $externalId);
    }

    public function test_status_reads_processing_success_and_failed(): void
    {
        $uuid = $this->gateway()->initiateCollection($this->request())->providerReference;
        $this->assertSame('pending', $this->gateway()->queryCollection($uuid)->status);

        $this->dalipaySettles($uuid, 'success');
        $status = $this->gateway()->queryCollection($uuid);
        $this->assertSame('succeeded', $status->status);
        $this->assertSame(6400, $status->amount);
        $this->assertNotNull($status->paidAt);

        $this->dalipaySettles($uuid, 'failed');
        $this->assertSame(['failed', 'declined'], [$this->gateway()->queryCollection($uuid)->status, $this->gateway()->queryCollection($uuid)->failureReason]);
        $this->assertSame("/api/v1/collections/{$uuid}/status", end($this->dalipaySent)['path']);
    }

    public function test_a_refused_request_fails_at_once_and_says_why(): void
    {
        $this->dalipayNextCreate = [400, ['success' => false, 'message' => 'account_number must be a valid Tanzanian phone number']];
        $this->assertSame(['failed', 'invalid_number'], [($r = $this->gateway()->initiateCollection($this->request()))->status, $r->failureReason]);

        $this->dalipayNextCreate = [401, ['success' => false, 'message' => 'Invalid API keys']];
        $this->assertSame('gateway_error', $this->gateway()->initiateCollection($this->request())->failureReason);
    }

    public function test_no_answer_is_unknown_not_failed_because_the_prompt_may_have_gone_out(): void
    {
        $this->dalipayNextCreate = 'no_answer';
        $result = $this->gateway()->initiateCollection($this->request());

        $this->assertTrue($result->unknown());
        $this->assertNull($result->providerReference);

        $this->dalipayNextCreate = [503, ['success' => false, 'message' => 'Service unavailable']];
        $this->assertTrue($this->gateway()->initiateCollection($this->request())->unknown());
    }

    /** @return array<string, array{array<string, mixed>, bool, string}> */
    public static function refusedConfigurations(): array
    {
        return [
            'production keys without DALIPAY_ENV=live' => [['public_key' => 'gw_pk_production_abc', 'secret_key' => 'gw_sk_production_def', 'env' => 'test'], false, 'unless DALIPAY_ENV=live'],
            'test keys with DALIPAY_ENV=live' => [['env' => 'live', 'base_url' => 'https://dalipay.test'], false, 'Test Dalipay keys are refused'],
            'one test and one production key' => [['secret_key' => 'gw_sk_production_def', 'env' => 'live'], false, 'different environments'],
            'swapped keys' => [['public_key' => self::DALIPAY_SECRET, 'secret_key' => self::DALIPAY_PUBLIC], false, 'swapped'],
            'no secret key' => [['secret_key' => ''], false, 'must both be set'],
            'an unknown environment' => [['env' => 'sandbox'], false, 'must be "test" or "live"'],
            'no base URL' => [['base_url' => ''], false, 'DALIPAY_BASE_URL'],
            'live over http' => [['public_key' => 'gw_pk_production_abc', 'secret_key' => 'gw_sk_production_def', 'env' => 'live', 'base_url' => 'http://dalipay.test'], false, 'https'],
            'test mode on a production server' => [[], true, 'APP_ENV=production'],
        ];
    }

    #[DataProvider('refusedConfigurations')]
    public function test_a_configuration_that_must_not_be_used_turns_pay_now_off_and_never_logs_a_key(array $config, bool $production, string $reason): void
    {
        $this->setUpDalipay($config);
        if ($production) {
            $this->app['env'] = 'production';
        }
        $logged = [];
        Log::listen(function ($message) use (&$logged) {
            $logged[] = $message->message;
        });

        $this->assertInstanceOf(UnavailablePaymentGateway::class, app(PaymentGateway::class));
        $this->assertFalse(app(PaymentGateway::class)->capabilities()->collections);

        $text = implode("\n", $logged);
        $this->assertStringContainsString($reason, $text);
        foreach ([self::DALIPAY_SECRET, self::DALIPAY_PUBLIC, 'gw_sk_production_def', 'gw_pk_production_abc', self::DALIPAY_WEBHOOK_SECRET] as $secret) {
            $this->assertStringNotContainsString($secret, $text);
        }
    }

    public function test_production_keys_are_accepted_with_dalipay_env_live(): void
    {
        $this->setUpDalipay(['public_key' => 'gw_pk_production_abc', 'secret_key' => 'gw_sk_production_def', 'env' => 'live']);
        $this->app['env'] = 'production';

        $gateway = app(PaymentGateway::class);
        $this->assertInstanceOf(DalipayGateway::class, $gateway);
        $this->assertFalse($gateway->capabilities()->sandbox);
    }

    public function test_the_secret_key_is_never_in_a_dump_of_the_gateway(): void
    {
        $dump = print_r(app(PaymentGatewayFactory::class)->dalipay(), true);
        ob_start();
        var_dump(app(PaymentGatewayFactory::class)->dalipay());
        $dump .= ob_get_clean();

        $this->assertStringNotContainsString(self::DALIPAY_SECRET, $dump);
    }
}
