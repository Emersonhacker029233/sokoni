<?php

namespace App\Console\Commands;

use App\Models\Update;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * "Update"/"Taarifa" auto-expires 24h after posting (CLAUDE.md Part 3) —
 * this is what actually enforces that. Hard-deletes rather than soft-hiding:
 * an Update has no lasting value once expired (unlike a Listing or a
 * review), and there's no "view an old Update" feature anywhere in the
 * spec to preserve rows for. Deletes one at a time (not a bulk query
 * delete) so each row's media file is cleaned up from storage alongside it.
 */
class DeleteExpiredUpdates extends Command
{
    protected $signature = 'updates:delete-expired';

    protected $description = 'Delete Updates past their 24h expiry, and their media files';

    public function handle(): int
    {
        $expired = Update::query()->where('expires_at', '<=', now())->get();

        foreach ($expired as $update) {
            foreach ([$update->media_path, $update->thumb_path] as $url) {
                if ($url) {
                    $relative = str($url)->after(Storage::disk('public')->url(''));
                    Storage::disk('public')->delete($relative);
                }
            }
            $update->delete();
        }

        $this->info("Deleted {$expired->count()} expired update(s).");

        return self::SUCCESS;
    }
}
