<?php

namespace App\Console\Commands;

use App\Services\Delivery\Bolt\BoltClient;
use App\Services\Delivery\DeliveryJobService;
use App\Services\Delivery\DeliveryPaymentService;
use Illuminate\Console\Command;

/**
 * Every minute from the cPanel cron (schedule:run), docs/delivery/BOLT.md:
 * settle delivery-fee payments, cancel deliveries whose fee was not paid in
 * time, retry Bolt quotes and bookings that hit a Bolt error, book ready
 * orders whose booking never ran, and collect Bolt receipts.
 *
 * Does nothing at all on a server with neither BOLT_ENABLED nor Bolt
 * credentials (production today), so not even a database query runs.
 * Existing deliveries keep progressing if BOLT_ENABLED is switched off
 * later, so no paid order is ever stranded.
 */
class DeliveryTick extends Command
{
    protected $signature = 'delivery:tick';

    protected $description = 'Settle delivery payments, expire unpaid deliveries, retry Bolt quotes and bookings, fetch Bolt receipts';

    public function handle(DeliveryJobService $jobs, DeliveryPaymentService $payments, BoltClient $bolt): int
    {
        if (! config('delivery.enabled') && ! $bolt->isConfigured()) {
            return self::SUCCESS;
        }

        // Payments first: a fee confirmed in the last minute must win over the unpaid timeout.
        $settled = $payments->refreshAllPending();
        $expired = $jobs->expireUnpaidJobs();
        $due = $jobs->processDue();

        foreach (['settled' => $settled, 'expired' => $expired, ...$due] as $what => $count) {
            if ($count > 0) {
                $this->info("{$what}: {$count}");
            }
        }

        return self::SUCCESS;
    }
}
