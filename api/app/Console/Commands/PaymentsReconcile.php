<?php

namespace App\Console\Commands;

use App\Services\Delivery\DeliveryPaymentService;
use App\Services\Payment\PaymentGatewayFactory;
use Illuminate\Console\Command;

/**
 * Every minute while Dalipay is configured (routes/console.php), whatever
 * the delivery flags say: asks Dalipay about every open payment attempt,
 * and again about recent attempts that timed out here, so a lost webhook
 * never strands a paid delivery fee or return ride fee. Confirmed payments
 * are applied exactly as from the app's polling or a webhook.
 */
class PaymentsReconcile extends Command
{
    protected $signature = 'payments:reconcile';

    protected $description = 'Check open and recently timed-out mobile-money payments with the provider';

    public function handle(DeliveryPaymentService $payments, PaymentGatewayFactory $gateways): int
    {
        if (config('payments.provider') !== 'dalipay' && ! $gateways->dalipayKeysPresent()) {
            return self::SUCCESS;
        }

        $settled = $payments->refreshAllPending();
        $late = $payments->recheckRecentFailures();

        foreach (['settled' => $settled, 'late' => $late] as $what => $count) {
            if ($count > 0) {
                $this->info("{$what}: {$count}");
            }
        }

        return self::SUCCESS;
    }
}
