<?php

namespace App\Services\Delivery;

use Kreait\Firebase\Contract\Auth;
use Kreait\Firebase\Contract\Database;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Factory;

/**
 * Lazily-built Firebase clients for the DELIVERY feature only. The existing
 * Sokoni 1.0 order pushes keep using LogPushNotifier untouched (client
 * decision 2026-10-29: real push for delivery only).
 *
 * Reads config('delivery.firebase.credentials') (FIREBASE_CREDENTIALS),
 * e.g. storage/app/firebase.json. Relative paths resolve against the
 * Laravel base path. The file is git-ignored and must never be committed.
 */
class FirebaseConnection
{
    private ?Factory $factory = null;

    private ?Messaging $messaging = null;

    private ?Database $database = null;

    private ?Auth $auth = null;

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

    public function hasDatabase(): bool
    {
        return $this->isConfigured() && filled(config('delivery.firebase.database_url'));
    }

    public function messaging(): Messaging
    {
        return $this->messaging ??= $this->factory()->createMessaging();
    }

    public function database(): Database
    {
        return $this->database ??= $this->factory()->createDatabase();
    }

    public function auth(): Auth
    {
        return $this->auth ??= $this->factory()->createAuth();
    }

    private function factory(): Factory
    {
        if ($this->factory === null) {
            $factory = (new Factory)->withServiceAccount((string) $this->credentialsPath());
            if (filled($url = config('delivery.firebase.database_url'))) {
                $factory = $factory->withDatabaseUri($url);
            }
            $this->factory = $factory;
        }

        return $this->factory;
    }
}
