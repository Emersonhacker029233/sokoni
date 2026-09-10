<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * C2 (tester feedback): "soft-delete products (recoverable), hard-delete
 * media only after retention window." A soft-deleted Product stays in the
 * database indefinitely (an admin can restore it via the Trashed filter),
 * but its photos/videos are real files that cost real storage — this is
 * what actually purges those, once a deletion is old enough that recovery
 * is unlikely to still matter. The product row itself is left alone
 * (still soft-deleted, still restorable) — only its media rows/files are
 * gone; a later restore just means a product with no photos, not a
 * missing product. Mirrors `DeleteExpiredUpdates`'s one-row-at-a-time
 * style so each row's storage file is cleaned up alongside it.
 */
class PurgeDeletedProductMedia extends Command
{
    public const RETENTION_DAYS = 30;

    protected $signature = 'products:purge-deleted-media';

    protected $description = 'Permanently delete the media files/rows of products soft-deleted more than 30 days ago';

    public function handle(): int
    {
        $products = Product::onlyTrashed()
            ->where('deleted_at', '<=', now()->subDays(self::RETENTION_DAYS))
            ->with('media')
            ->get();

        $purgedMediaCount = 0;

        foreach ($products as $product) {
            foreach ($product->media as $media) {
                foreach ([$media->path, $media->thumb_path, $media->card_path] as $url) {
                    if ($url) {
                        $relative = str($url)->after(Storage::disk('public')->url(''));
                        Storage::disk('public')->delete($relative);
                    }
                }
                $media->delete();
                $purgedMediaCount++;
            }
        }

        $this->info("Purged media for {$products->count()} product(s), {$purgedMediaCount} media file(s) total.");

        return self::SUCCESS;
    }
}
