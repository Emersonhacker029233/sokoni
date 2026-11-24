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
 * unavailable gateway: "Pay now" disappears and the reason is logged,
 * without any key.
 *
 * Dalipay LIVE keys on a server that isn't production (staging, a PC)
 * never power the app's Pay now: only the guarded staging live test
 * (StagingPaymentTestController) may create a live collection there.
 * Existing live payments are still confirmed (forProvider).
 */
class PaymentGatewayFactory
{
    public function current(): PaymentGateway
    {
        $provider = (string) config('payments.provider');
        if ($provider !== 'dalipay') {
            return $this->forProvider($provider);
        }

        $gateway = $this->dalipay();
        if ($gateway !== null && ! $gateway->capabilities()->sandbox && ! app()->environment('production')) {
            $this->warn('Dalipay live keys on a non-production server: Pay now stays off. Only the staging live test (STAGING_PAYMENTS_ALLOW_LIVE) may use them.');

            return new UnavailablePaymentGateway;
        }

        return $gateway ?? new UnavailablePaymentGateway;
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

    /** Dalipay with valid credentials, test or live, or null (the reason is logged). */
    public function dalipay(): ?DalipayGateway
    {
        try {
            return new DalipayGateway(DalipayConfig::fromArray((array) config('payments.dalipay'), app()->environment('production')));
        } catch (InvalidArgumentException $e) {
            if ($this->dalipayKeysPresent() || config('payments.provider') === 'dalipay') {
                $this->warn('Dalipay is not used: '.$e->getMessage(), critical: true);
            }

            return null;
        }
    }

    /** Whether anything Dalipay-related is in .env at all (the reconcile cron runs only then). */
    public function dalipayKeysPresent(): bool
    {
        return filled(config('payments.dalipay.public_key')) || filled(config('payments.dalipay.secret_key'));
    }

    /** Logged at most every 10 minutes per reason, not on every request. Never contains a key. */
    private function warn(string $message, bool $critical = false): void
    {
        if (! Cache::add('payments:dalipay-refused:'.md5($message), true, 600)) {
            return;
        }
        $critical ? Log::critical('[payments] '.$message) : Log::warning('[payments] '.$message);
    }
}
