<?php

namespace App\Services\Payment;

/**
 * Bound when no provider is configured (PAYMENT_PROVIDER unset or "none"),
 * and in production whenever the configured provider is the sandbox fake.
 * Reports no collections, so the apps never show "Pay now"; a direct call
 * fails loudly instead of pretending a prompt was sent.
 */
class UnavailablePaymentGateway implements PaymentGateway
{
    public function name(): string
    {
        return 'none';
    }

    public function capabilities(): GatewayCapabilities
    {
        return new GatewayCapabilities(collections: false, sandbox: false);
    }

    public function initiateCollection(CollectionRequest $request): CollectionResult
    {
        throw new PaymentUnavailableException('No payment provider is configured.');
    }

    public function queryCollection(string $providerReference): CollectionStatus
    {
        throw new PaymentUnavailableException('No payment provider is configured.');
    }
}
