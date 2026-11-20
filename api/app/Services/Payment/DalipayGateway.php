<?php

namespace App\Services\Payment;

use App\Services\Payment\Dalipay\DalipayConfig;
use App\Services\Payment\Dalipay\DalipayException;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Dalipay mobile-money collections (https://dalipay.co.tz/documentation):
 * a USSD push to the buyer's phone for the Bolt delivery fee or the return
 * ride fee, and the status of that push.
 *
 *  - POST /api/v1/collections {account_number, amount, currency, provider,
 *    external_id} with X-Public-Key / X-Secret-Key. 201 answers
 *    `data.uuid` and status `processing`.
 *  - GET /api/v1/collections/{uuid}/status: processing | success | failed.
 *
 * `external_id` (at most 30 characters) is our attempt's idempotency key,
 * or a hash of it when it is longer, so a lost answer can still be matched
 * from Dalipay's webhook. No customer name is sent: Dalipay doesn't need it.
 *
 * Logs carry the HTTP status and Dalipay's message, never a key, the
 * request headers or the full phone number.
 */
class DalipayGateway implements PaymentGateway
{
    public function __construct(private readonly DalipayConfig $config) {}

    public function name(): string
    {
        return 'dalipay';
    }

    public function capabilities(): GatewayCapabilities
    {
        return new GatewayCapabilities(collections: true, sandbox: $this->config->isTest(), channels: array_keys($this->config->providers));
    }

    /** Dalipay's `external_id` for an attempt: at most 30 characters, letters, digits, - and _. */
    public static function externalId(string $reference): string
    {
        return strlen($reference) <= 30 && preg_match('/^[A-Za-z0-9_-]+$/', $reference)
            ? $reference
            : 'skh'.substr(hash('sha256', $reference), 0, 27);
    }

    /** +255754123456 -> 0754123456, the format Dalipay's examples use. */
    public static function localNumber(string $msisdn): string
    {
        $digits = (string) preg_replace('/\D/', '', $msisdn);

        return str_starts_with($digits, '255') ? '0'.substr($digits, 3) : $digits;
    }

    public function initiateCollection(CollectionRequest $request): CollectionResult
    {
        $provider = $this->config->providers[$request->channel] ?? null;
        if ($provider === null) {
            return new CollectionResult('failed', failureReason: 'unsupported_channel');
        }
        $externalId = self::externalId($request->reference);

        try {
            $response = $this->http()->post($this->config->url('collections'), [
                'account_number' => self::localNumber($request->msisdn),
                'amount' => $request->amount,
                'currency' => $request->currency,
                'provider' => $provider,
                'external_id' => $externalId,
            ]);
        } catch (ConnectionException $e) {
            Log::warning("[payments] dalipay: no answer creating collection {$externalId}; the prompt may or may not have been sent");

            return new CollectionResult('unknown', meta: ['external_id' => $externalId]);
        }

        $data = (array) $response->json('data', []);
        if ($response->successful() && is_string($data['uuid'] ?? null) && $data['uuid'] !== '') {
            $meta = array_filter([
                'external_id' => $externalId,
                'dalipay_reference' => is_string($data['reference'] ?? null) ? $data['reference'] : null,
                'dalipay_status' => is_string($data['status'] ?? null) ? $data['status'] : null,
                'dalipay_provider' => $provider,
            ]);

            return ($data['status'] ?? null) === 'failed'
                ? new CollectionResult('failed', $data['uuid'], 'declined', $meta)
                : new CollectionResult('pending', $data['uuid'], meta: $meta);
        }

        if ($response->serverError() || $response->successful()) {
            // 5xx, or a 2xx without a collection: Dalipay may still have sent the prompt.
            Log::warning("[payments] dalipay: HTTP {$response->status()} creating collection {$externalId}: ".$this->message($response));

            return new CollectionResult('unknown', meta: ['external_id' => $externalId]);
        }

        Log::error("[payments] dalipay: refused collection {$externalId} (HTTP {$response->status()}): ".$this->message($response));

        return new CollectionResult('failed', failureReason: match (true) {
            in_array($response->status(), [401, 403], true) => 'gateway_error',
            str_contains($this->message($response), 'account_number') => 'invalid_number',
            default => 'rejected',
        }, meta: ['external_id' => $externalId]);
    }

    public function queryCollection(string $providerReference): CollectionStatus
    {
        try {
            $response = $this->http()->get($this->config->url('collections/'.rawurlencode($providerReference).'/status'));
        } catch (ConnectionException $e) {
            throw new DalipayException("no answer for collection {$providerReference}", previous: $e);
        }
        if (! $response->successful()) {
            throw new DalipayException("HTTP {$response->status()} for collection {$providerReference}: ".$this->message($response));
        }

        $data = (array) $response->json('data', []);
        if (($data['uuid'] ?? $providerReference) !== $providerReference) {
            throw new DalipayException("status answer for another collection than {$providerReference}");
        }

        return self::status($data);
    }

    /**
     * Dalipay's collection fields (status answer or webhook `data`) as a
     * CollectionStatus. Unknown statuses read as pending, never as paid.
     *
     * @param  array<string, mixed>  $data
     */
    public static function status(array $data): CollectionStatus
    {
        $meta = array_filter([
            'dalipay_reference' => is_string($data['reference'] ?? null) ? $data['reference'] : null,
            'dalipay_status' => is_string($data['status'] ?? null) ? $data['status'] : null,
        ]);
        $amount = is_numeric($data['amount'] ?? null) ? (int) round((float) $data['amount']) : null;

        return match ($data['status'] ?? null) {
            'success' => new CollectionStatus('succeeded', paidAt: self::time($data['updated_at'] ?? null), amount: $amount, meta: $meta),
            'failed' => new CollectionStatus('failed', 'declined', amount: $amount, meta: $meta),
            default => new CollectionStatus('pending', amount: $amount, meta: $meta),
        };
    }

    private static function time(mixed $value): ?CarbonImmutable
    {
        try {
            return is_string($value) && $value !== '' ? CarbonImmutable::parse($value) : null;
        } catch (Throwable) {
            return null;
        }
    }

    private function http(): \Illuminate\Http\Client\PendingRequest
    {
        return Http::withHeaders($this->config->headers())
            ->asJson()
            ->connectTimeout(min(10, $this->config->timeoutSeconds))
            ->timeout($this->config->timeoutSeconds);
    }

    /** Dalipay's own error message, shortened, for the log. */
    private function message(Response $response): string
    {
        $message = $response->json('message');

        return mb_substr(is_string($message) ? $message : (string) $response->body(), 0, 200);
    }
}
