<?php

namespace App\Services\Delivery\Bolt;

/**
 * GET /rides/receipt: what the ride finally cost Sokoni's Bolt Business
 * account. Usually ready within minutes of COMPLETED; Bolt says a price
 * review can take 24 hours or longer.
 */
final class BoltReceipt
{
    /** @param list<array{type: string, amount: float}> $breakdown */
    public function __construct(
        public readonly float $amount,
        public readonly ?string $currency,
        public readonly ?string $invoiceUrl,
        public readonly array $breakdown,
    ) {}

    public static function fromArray(array $body): self
    {
        if (! is_numeric($body['amount'] ?? null)) {
            throw BoltApiException::malformed('receipt', 'no amount');
        }

        $breakdown = [];
        foreach ((array) ($body['fare_breakdown'] ?? []) as $line) {
            if (is_array($line) && is_numeric($line['amount'] ?? null)) {
                $breakdown[] = ['type' => (string) ($line['type'] ?? 'other'), 'amount' => (float) $line['amount']];
            }
        }

        return new self(
            (float) $body['amount'],
            isset($body['currency_code']) ? strtoupper((string) $body['currency_code']) : null,
            is_string($body['invoice_url'] ?? null) ? $body['invoice_url'] : null,
            $breakdown,
        );
    }

    public function toArray(): array
    {
        return [
            'amount' => $this->amount,
            'currency' => $this->currency,
            'invoice_url' => $this->invoiceUrl,
            'fare_breakdown' => $this->breakdown,
        ];
    }
}
