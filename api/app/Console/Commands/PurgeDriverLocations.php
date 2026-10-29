<?php

namespace App\Console\Commands;

use App\Models\DriverLocation;
use App\Support\DeliverySettings;
use Illuminate\Console\Command;

/**
 * Privacy: driver breadcrumbs exist only for dispute handling and are
 * deleted after `delivery_location_retention_hours` (72 h, client-approved).
 * Runs hourly. Runs even with the feature flag off, so nothing ever
 * lingers after delivery is switched off.
 */
class PurgeDriverLocations extends Command
{
    protected $signature = 'delivery:purge-locations';

    protected $description = 'Delete driver location breadcrumbs older than the retention window';

    public function handle(): int
    {
        $cutoff = now()->subHours(DeliverySettings::locationRetentionHours());
        $deleted = 0;

        // Chunked deletes keep each statement short on shared MySQL.
        do {
            $batch = DriverLocation::query()->where('recorded_at', '<', $cutoff)->limit(1000)->pluck('id');
            if ($batch->isNotEmpty()) {
                $deleted += DriverLocation::query()->whereIn('id', $batch)->delete();
            }
        } while ($batch->count() === 1000);

        if ($deleted > 0) {
            $this->info("Deleted {$deleted} location point(s).");
        }

        return self::SUCCESS;
    }
}
