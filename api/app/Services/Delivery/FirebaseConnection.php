<?php

namespace App\Services\Delivery;

use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Factory;

/**
 * Lazily-built Firebase Cloud Messaging client for delivery and return
 * pushes only. The existing order pushes keep using LogPushNotifier
 * untouched (client decision 2026-10-29: real push for delivery only).
 *
 * Reads config('delivery.firebase.credentials') (FIREBASE_CREDENTIALS),
 * e.g. storage/app/firebase.json. Relative paths resolve against the
 * Laravel base path. The file is git-ignored and must never be committed.
 */
class FirebaseConnection
{
    private ?Messaging $messaging = null;

    public function credentialsPath(): ?string
    {
        $path = config('delivery.firebase.credentials');
        if (! is_string($path) || trim($path) === '') {
            return null;
        }
        $isAbsolute = str_starts_with($path, '/') || preg_match('/^[A-Za-z]:/', $path) === 1;
        $absolute = $isAbsolute ? $path : base_path($path);

        return is_file($absolute) ? $absolute : null;
    }

    public function isConfigured(): bool
    {
        return $this->credentialsPath() !== null;
    }

    public function messaging(): Messaging
    {
        return $this->messaging ??= (new Factory)->withServiceAccount((string) $this->credentialsPath())->createMessaging();
    }
}
