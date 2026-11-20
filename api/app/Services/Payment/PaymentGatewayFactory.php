<?php

namespace App\Services\Payment;

use App\Services\Payment\Dalipay\DalipayConfig;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * Which gateway takes new payments (PAYMENT_PROVIDER), and which one an
 * existing payment must be asked about (its own `provider`). The two can
 * differ: a Dalipay payment still pending when PAYMENT_PROVIDER changes is
 * still confirmed with Dalipay while its keys are in .env.
 *
 * A configuration that must not be used (DalipayConfig) gives the
 * unavailable gateway: "Pay now" disappears and the reason is logged once,
 * without any key.
 */
class PaymentGatewayFactory
{
    public function current(): PaymentGateway
    {
        return $this->forProvider((string) config('payments.provider'));
    }

    public function forProvider(string $provider): PaymentGateway
    {
        return match ($provider) {
            'fake' => app()->environment('production')
                ? new UnavailablePaymentGateway
                : new FakePaymentGateway((int) config('payments.fake.approve_after_seconds')),
            'dalipay' => $this->dalipay() ?? new UnavailablePaymentGateway,
            default => new UnavailablePaymentGateway,
        };
    }

    /** Dalipay with valid credentials, or null (the reason is logged). */
    public function dalipay(): ?DalipayGateway
    {
        try {
            return new DalipayGateway(DalipayConfig::fromArray((array) config('payments.dalipay'), app()->environment('production')));
        } catch (InvalidArgumentException $e) {
            // Logged at most every 10 minutes per reason, not on every request.
            if (($this->dalipayKeysPresent() || config('payments.provider') === 'dalipay')
                && Cache::add('payments:dalipay-refused:'.md5($e->getMessage()), true, 600)) {
                Log::critical('[payments] Dalipay is not used: '.$e->getMessage());
            }

            return null;
        }
    }

    /** Whether anything Dalipay-related is in .env at all (the reconcile cron runs only then). */
    public function dalipayKeysPresent(): bool
    {
        return filled(config('payments.dalipay.public_key')) || filled(config('payments.dalipay.secret_key'));
    }
}
