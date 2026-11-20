<?php

namespace App\Services\Payment\Dalipay;

use App\Models\Payment;
use App\Models\PaymentWebhookEvent;
use App\Services\Delivery\DeliveryPaymentService;
use App\Services\Payment\DalipayGateway;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;

/**
 * Dalipay's webhook (POST /api/payments/webhook/dalipay): `collection.success`
 * or `collection.failed` with the collection's uuid, reference, amount and
 * our external_id, signed with HMAC-SHA256 of the raw body in X-Signature.
 *
 * Safe against forgery, duplicates and replays:
 *  1. The signature is checked against DALIPAY_WEBHOOK_SECRET in constant
 *     time before anything else. A bad one is answered 401 and only logged,
 *     never stored, so a forger can't take the event id of a genuine event.
 *  2. Dalipay sends no event id or timestamp, so the event id is
 *     "{uuid}:{event}" and payment_webhook_events' unique key makes a
 *     duplicate (or a replay of the same body) a no-op answered 200.
 *  3. The body is only a hint. The payment is confirmed by asking Dalipay's
 *     status API (DeliveryPaymentService::confirm), and a payment only ever
 *     moves towards "paid", so a replayed or reordered event can't undo or
 *     invent anything.
 *  4. Unknown collections are recorded and ignored. Amounts that don't match
 *     the attempt are never applied.
 *
 * Dalipay doesn't retry webhooks; the minute cron (payments:reconcile)
 * asks about every open attempt anyway.
 */
class DalipayWebhookHandler
{
    public const EVENTS = ['collection.success', 'collection.failed'];

    public function __construct(private readonly DeliveryPaymentService $payments) {}

    /** @return array{0: int, 1: array<string, mixed>} HTTP status and JSON body */
    public function handle(string $rawBody, ?string $signature): array
    {
        $secret = (string) config('payments.dalipay.webhook_secret');
        if ($secret === '') {
            Log::warning('[payments] dalipay webhook refused: DALIPAY_WEBHOOK_SECRET is not set');

            return [401, ['message' => 'Webhook not configured.']];
        }
        if (! self::signatureValid($rawBody, $signature, $secret)) {
            Log::warning('[payments] dalipay webhook refused: invalid signature ('.strlen($rawBody).' bytes)');

            return [401, ['message' => 'Invalid signature.']];
        }

        $payload = json_decode($rawBody, true);
        $event = is_array($payload) ? ($payload['event'] ?? null) : null;
        $data = is_array($payload) && is_array($payload['data'] ?? null) ? $payload['data'] : [];
        $uuid = is_string($data['uuid'] ?? null) ? $data['uuid'] : null;

        $eventId = $uuid !== null && is_string($event)
            ? mb_substr("{$uuid}:{$event}", 0, 150)
            : 'unparsed:'.hash('sha256', $rawBody);

        try {
            $stored = PaymentWebhookEvent::query()->create([
                'provider' => 'dalipay',
                'event_id' => $eventId,
                'payload' => is_array($payload) ? self::masked($payload) : ['raw_length' => strlen($rawBody)],
                'signature_valid' => true,
            ]);
        } catch (QueryException $e) {
            if (PaymentWebhookEvent::query()->where('provider', 'dalipay')->where('event_id', $eventId)->exists()) {
                return [200, ['received' => true, 'duplicate' => true]];
            }
            throw $e;
        }

        $result = $this->process($event, $data, $uuid);
        $stored->forceFill(['result' => $result, 'processed_at' => now()])->save();

        return [200, ['received' => true]];
    }

    public static function signatureValid(string $rawBody, ?string $signature, string $secret): bool
    {
        $signature = strtolower(trim((string) $signature));
        if ($signature === '' || ! ctype_xdigit($signature)) {
            return false;
        }

        return hash_equals(hash_hmac('sha256', $rawBody, $secret), $signature);
    }

    /** @param array<string, mixed> $data */
    private function process(mixed $event, array $data, ?string $uuid): string
    {
        if (! in_array($event, self::EVENTS, true)) {
            return 'ignored_event';
        }
        if ($uuid === null) {
            return 'malformed';
        }

        $payment = $this->find($uuid, is_string($data['external_id'] ?? null) ? $data['external_id'] : null);
        if ($payment === null) {
            Log::notice("[payments] dalipay webhook for unknown collection {$uuid}");

            return 'unknown_payment';
        }

        $amount = is_numeric($data['amount'] ?? null) ? (int) round((float) $data['amount']) : null;
        if ($amount !== null && $amount !== (int) $payment->amount) {
            Log::critical("[payments] dalipay webhook for payment #{$payment->id} says {$amount}, the attempt was {$payment->amount}; not applied");

            return 'amount_mismatch';
        }

        if ($payment->provider_reference === null) {
            // The answer to "send the prompt" was lost; the webhook tells us which collection it was.
            Payment::query()->whereKey($payment->id)->whereNull('provider_reference')->update(['provider_reference' => $uuid]);
        }

        $before = $payment->status;
        $after = $this->payments->confirm($payment)->status;

        return $before === $after ? "unchanged:{$after}" : "{$before}->{$after}";
    }

    /** By Dalipay's uuid, else by our external_id (when the uuid never reached us). */
    private function find(string $uuid, ?string $externalId): ?Payment
    {
        $payment = Payment::query()->where('provider', 'dalipay')->where('provider_reference', $uuid)->first();
        if ($payment !== null || $externalId === null) {
            return $payment;
        }

        $byKey = Payment::query()->where('provider', 'dalipay')->where('idempotency_key', $externalId)->whereNull('provider_reference')->first();
        if ($byKey !== null) {
            return $byKey;
        }

        // A key longer than 30 characters went out hashed (DalipayGateway::externalId).
        return Payment::query()->where('provider', 'dalipay')->whereNull('provider_reference')
            ->where('created_at', '>=', now()->subDays(2))
            ->get()
            ->first(fn (Payment $p) => DalipayGateway::externalId($p->idempotency_key) === $externalId);
    }

    /**
     * The payload as stored: Dalipay's webhook carries no phone number
     * today, but any field that looks like one is masked in case it does.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private static function masked(array $payload): array
    {
        array_walk_recursive($payload, function (&$value, $key) {
            if (in_array($key, ['account_number', 'msisdn', 'phone', 'phone_number'], true) && is_string($value) && strlen($value) > 6) {
                $value = substr($value, 0, 4).str_repeat('•', strlen($value) - 7).substr($value, -3);
            }
        });

        return $payload;
    }
}
