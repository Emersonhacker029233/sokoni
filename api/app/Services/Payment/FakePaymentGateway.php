<?php

namespace App\Services\Payment;

use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

/**
 * MOCK: sandbox stand-in for the real provider (Dalipay, Part B), used on
 * local and staging only. Never bound in production (see
 * AppServiceProvider), so it can't confirm a real order.
 *
 * Behaves like a USSD push: the prompt is "sent" at once and the payer
 * "answers" approve_after_seconds later. The outcome is chosen by the last
 * four digits of the paying number, as WALLET_PLAN.md section 6 describes:
 *   ...0001  the payer declines         -> failed (declined)
 *   ...0002  the payer never answers    -> stays pending until the attempt times out
 *   anything else                       -> succeeded
 *
 * Stateless: the outcome and send time live in the provider reference,
 * which only the server stores and only the server queries.
 */
class FakePaymentGateway implements PaymentGateway
{
    public function __construct(private readonly int $approveAfterSeconds = 8) {}

    public function name(): string
    {
        return 'fake';
    }

    public function capabilities(): GatewayCapabilities
    {
        return new GatewayCapabilities(collections: true, sandbox: true, channels: (array) config('payments.channels'));
    }

    public function initiateCollection(CollectionRequest $request): CollectionResult
    {
        $outcome = match (substr((string) preg_replace('/\D/', '', $request->msisdn), -4)) {
            '0001' => 'decline',
            '0002' => 'silent',
            default => 'approve',
        };

        return new CollectionResult('pending', sprintf('FAKE-%s-%d-%s', $outcome, now()->getTimestamp(), Str::upper(Str::random(10))));
    }

    public function queryCollection(string $providerReference): CollectionStatus
    {
        if (! preg_match('/^FAKE-(approve|decline|silent)-(\d+)-[A-Za-z0-9]+$/', $providerReference, $m)) {
            return new CollectionStatus('failed', 'unknown_reference');
        }

        $answeredAt = CarbonImmutable::createFromTimestamp((int) $m[2] + $this->approveAfterSeconds);
        if ($m[1] === 'silent' || now()->lt($answeredAt)) {
            return new CollectionStatus('pending');
        }

        return $m[1] === 'decline'
            ? new CollectionStatus('failed', 'declined')
            : new CollectionStatus('succeeded', paidAt: $answeredAt);
    }
}
