<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Short-lived signed URL for a file on the PRIVATE `local` disk (driver
 * photos, proof-of-delivery). ID and licence images are never exposed
 * through this. Only the admin panel reads those.
 */
class PrivateFileUrl
{
    public static function for(?string $path, int $minutes = 30): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        try {
            return Storage::disk('local')->temporaryUrl($path, now()->addMinutes($minutes));
        } catch (Throwable) {
            return null;
        }
    }
}
