<?php

namespace App\Console\Commands;

use App\Services\Delivery\DispatchService;
use Illuminate\Console\Command;

/**
 * Every minute from the cPanel cron (schedule:run). Advances dispatch waves
 * that timed out (3 km -> 5 km -> 8 km -> no driver found) and offers open
 * waves to drivers who came online since. Request paths also advance
 * waves lazily, so this is the safety net, not the only clock.
 */
class DeliveryDispatchTick extends Command
{
    protected $signature = 'delivery:dispatch-tick';

    protected $description = 'Advance delivery dispatch waves and top up open offers';

    public function handle(DispatchService $dispatch): int
    {
        if (! config('delivery.enabled')) {
            return self::SUCCESS;
        }

        $advanced = $dispatch->advanceAllDue();
        $dispatch->topUpOpenWaves();

        if ($advanced > 0) {
            $this->info("Advanced {$advanced} job(s).");
        }

        return self::SUCCESS;
    }
}
