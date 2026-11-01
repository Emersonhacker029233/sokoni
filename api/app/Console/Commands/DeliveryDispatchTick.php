<?php

namespace App\Console\Commands;

use App\Services\Delivery\DeliveryJobService;
use App\Services\Delivery\DispatchService;
use Illuminate\Console\Command;

/**
 * Every minute from the cPanel cron (schedule:run). Cancels jobs whose
 * delivery fee was not paid within the timeout (default 20 minutes),
 * advances dispatch waves that timed out (3 km -> 5 km -> 8 km -> no
 * driver found) and offers open waves to drivers who came online since.
 * Request paths also advance waves lazily, so this is the safety net,
 * not the only clock.
 */
class DeliveryDispatchTick extends Command
{
    protected $signature = 'delivery:dispatch-tick';

    protected $description = 'Expire unpaid delivery jobs, advance dispatch waves and top up open offers';

    public function handle(DispatchService $dispatch, DeliveryJobService $jobs): int
    {
        if (! config('delivery.enabled')) {
            return self::SUCCESS;
        }

        $expired = $jobs->expireUnpaidJobs();
        $advanced = $dispatch->advanceAllDue();
        $dispatch->topUpOpenWaves();

        if ($expired > 0) {
            $this->info("Cancelled {$expired} unpaid job(s).");
        }
        if ($advanced > 0) {
            $this->info("Advanced {$advanced} job(s).");
        }

        return self::SUCCESS;
    }
}
