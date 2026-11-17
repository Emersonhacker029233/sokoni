<?php

namespace App\Console\Commands;

use App\Services\Delivery\Bolt\BoltClient;
use App\Services\Delivery\DeliveryJobService;
use Illuminate\Console\Command;

/**
 * Bolt ride tracking: reads the details of every running Bolt ride (state,
 * driver, tracking link) and moves its delivery along. Scheduled every 10
 * seconds, Bolt's fair-use limit for ride details, only while Bolt is
 * switched on (routes/console.php). The buyer's and seller's own polls
 * refresh a ride too, within the same limit. Webhooks, once Bolt enables
 * them, make this a safety net.
 */
class DeliveryTrack extends Command
{
    protected $signature = 'delivery:track';

    protected $description = 'Poll Bolt for every running ride and apply its state';

    public function handle(DeliveryJobService $jobs, BoltClient $bolt): int
    {
        if (! $bolt->isConfigured()) {
            return self::SUCCESS;
        }

        $synced = $jobs->syncActive();
        if ($synced > 0 && $this->output->isVerbose()) {
            $this->info("Synced {$synced} ride(s).");
        }

        return self::SUCCESS;
    }
}
