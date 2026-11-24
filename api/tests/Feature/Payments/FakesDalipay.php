<?php

namespace Tests\Feature\Payments;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

/**
 * Dalipay as its documentation describes it (https://dalipay.co.tz/documentation),
 * answered from memory with Http::fake:
 *  - POST /api/v1/collections -> 201 {success, data: {uuid, reference, status: processing, ...}}
 *  - GET /api/v1/collections/{uuid}/status -> 200 {success, data: {..., status}}
 *  - the webhook: {event, data} signed with HMAC-SHA256 of the raw body (X-Signature, hex).
 */
trait FakesDalipay
{
    protected const DALIPAY_URL = 'https://dalipay.test';

    protected const DALIPAY_PUBLIC = 'gw_pk_test_PUBLICabc123';

    protected const DALIPAY_SECRET = 'gw_sk_test_SECRETxyz789';

    protected const DALIPAY_WEBHOOK_SECRET = 'cbsec_WEBHOOKqrs456';

    /** @var array<string, array<string, mixed>> uuid => collection */
    protected array $dalipayCollections = [];

    /** @var list<array{method: string, path: string, headers: array, body: array}> */
    protected array $dalipaySent = [];

    /** What the next create answers instead of a collection: 'no_answer', or [status, body]. */
    protected mixed $dalipayNextCreate = null;

    protected function setUpDalipay(array $config = []): void
    {
        config([
            'payments.provider' => 'dalipay',
            'payments.attempt_timeout_seconds' => 120,
            'payments.status_query_interval_seconds' => 1,
            'payments.dalipay' => [
                'env' => 'test',
                'base_url' => self::DALIPAY_URL,
                'public_key' => self::DALIPAY_PUBLIC,
                'secret_key' => self::DALIPAY_SECRET,
                'webhook_secret' => self::DALIPAY_WEBHOOK_SECRET,
                'timeout_seconds' => 15,
                'late_check_minutes' => 60,
                'providers' => ['mpesa' => 'Mpesa', 'airtel' => 'Airtel', 'mixx' => 'Tigo', 'halopesa' => 'Halopesa', 'azampesa' => 'Azampesa'],
                ...$config,
            ],
        ]);

        Http::fake(function (Request $request) {
            $host = parse_url($request->url(), PHP_URL_HOST);
            if ($host !== parse_url(self::DALIPAY_URL, PHP_URL_HOST)) {
                return null;
            }
            $path = (string) parse_url($request->url(), PHP_URL_PATH);
            $this->dalipaySent[] = ['method' => $request->method(), 'path' => $path, 'headers' => $request->headers(), 'body' => $request->data()];

            if ($request->header('X-Public-Key')[0] ?? null) {
                if (($request->header('X-Secret-Key')[0] ?? null) !== config('payments.dalipay.secret_key')) {
                    return Http::response(['success' => false, 'message' => 'Invalid API keys'], 401);
                }
            }

            if ($request->method() === 'POST' && $path === '/api/v1/collections') {
                $next = $this->dalipayNextCreate;
                $this->dalipayNextCreate = null;
                if ($next === 'no_answer') {
                    // The request reached Dalipay (the prompt went out) but the answer was lost.
                    $this->dalipayCreate($request->data());

                    return Http::failedConnection('cURL error 28: Operation timed out');
                }
                if (is_array($next)) {
                    return Http::response($next[1], $next[0]);
                }
                $collection = $this->dalipayCreate($request->data());

                return Http::response([
                    'success' => true,
                    'message' => 'Checkout request sent to customer (test mode - simulated)',
                    'data' => array_intersect_key($collection, array_flip(['uuid', 'reference', 'status', 'amount', 'currency', 'provider', 'external_id'])),
                ], 201);
            }

            if ($request->method() === 'GET' && preg_match('#^/api/v1/collections/([^/]+)/status$#', $path, $m)) {
                $collection = $this->dalipayCollections[$m[1]] ?? null;

                return $collection === null
                    ? Http::response(['success' => false, 'message' => 'Collection not found'], 404)
                    : Http::response(['success' => true, 'data' => $collection]);
            }

            return Http::response(['success' => false, 'message' => 'Not found'], 404);
        });
    }

    /** @param array<string, mixed> $body */
    private function dalipayCreate(array $body): array
    {
        $uuid = (string) Str::uuid();

        return $this->dalipayCollections[$uuid] = [
            'uuid' => $uuid,
            'reference' => 'col_'.Str::random(22),
            'status' => 'processing',
            'amount' => $body['amount'] ?? null,
            'currency' => $body['currency'] ?? 'TZS',
            // Dalipay takes the network from the number ("we don't need provider, just the number").
            'provider' => self::networkOf((string) ($body['account_number'] ?? '')),
            'external_id' => $body['external_id'] ?? null,
            'created_at' => now()->utc()->format('Y-m-d\TH:i:s\Z'),
            'updated_at' => now()->utc()->format('Y-m-d\TH:i:s\Z'),
        ];
    }

    /** The network Dalipay records for a local number (enough for the tests: 074-076 Vodacom, 068/069/078 Airtel, 065/067/071/077 Yas, 061/062 Halotel). */
    protected static function networkOf(string $local): string
    {
        return match (substr($local, 0, 3)) {
            '068', '069', '078' => 'Airtel',
            '065', '067', '071', '077' => 'Tigo',
            '061', '062' => 'Halopesa',
            default => 'Mpesa',
        };
    }

    /** The payer answers the prompt: Dalipay's status becomes success or failed. */
    protected function dalipaySettles(string $uuid, string $status): array
    {
        $this->dalipayCollections[$uuid]['status'] = $status;
        $this->dalipayCollections[$uuid]['updated_at'] = now()->utc()->format('Y-m-d\TH:i:s\Z');

        return $this->dalipayCollections[$uuid];
    }

    protected function lastDalipayUuid(): string
    {
        return (string) array_key_last($this->dalipayCollections);
    }

    protected function dalipayStatusQueries(): int
    {
        return count(array_filter($this->dalipaySent, fn ($r) => $r['method'] === 'GET'));
    }

    /** Dalipay's webhook body for a collection, as its docs show it. */
    protected function webhookBody(array $collection, ?string $event = null): string
    {
        return json_encode([
            'event' => $event ?? 'collection.'.$collection['status'],
            'data' => array_intersect_key($collection, array_flip(['reference', 'uuid', 'status', 'amount', 'currency', 'external_id'])),
        ]);
    }

    protected function postWebhook(string $body, ?string $signature = null): TestResponse
    {
        $server = ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'];
        $server['HTTP_X_SIGNATURE'] = $signature ?? hash_hmac('sha256', $body, self::DALIPAY_WEBHOOK_SECRET);

        return $this->call('POST', '/api/payments/webhook/dalipay', [], [], [], $server, $body);
    }
}
