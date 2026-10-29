<?php

namespace App\Services\Delivery;

use App\Models\DeliveryJob;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Firebase Realtime Database channel at /tracking/{jobId}:
 *   members/{uid}: "buyer" or "driver"   (written by Laravel only)
 *   driver_uid: "<uid>"                  (written by Laravel only)
 *   loc: {lat, lng, heading, ts}         (written by the driver app only)
 * Security rules: docs/delivery/firebase-database.rules.json.
 */
class FirebaseLiveTracking implements LiveTracking
{
    public function __construct(private readonly FirebaseConnection $firebase) {}

    public static function uidFor(User $user): string
    {
        return 'sokoni_'.$user->id;
    }

    public function isEnabled(): bool
    {
        return true;
    }

    public function open(DeliveryJob $job): void
    {
        $job->loadMissing(['order.buyer', 'driver.user']);
        if ($job->driver === null) {
            return;
        }

        try {
            $driverUid = static::uidFor($job->driver->user);
            $this->firebase->database()->getReference("tracking/{$job->id}")->set([
                'members' => [
                    static::uidFor($job->order->buyer) => 'buyer',
                    $driverUid => 'driver',
                ],
                'driver_uid' => $driverUid,
                'opened_at' => now()->getTimestampMs(),
            ]);
        } catch (Throwable $e) {
            Log::warning("[delivery] could not open tracking for job #{$job->id}: ".$e->getMessage());
        }
    }

    public function close(DeliveryJob $job): void
    {
        try {
            $this->firebase->database()->getReference("tracking/{$job->id}")->remove();
        } catch (Throwable $e) {
            Log::warning("[delivery] could not close tracking for job #{$job->id}: ".$e->getMessage());
        }
    }

    public function credentialsFor(User $user): ?array
    {
        try {
            $uid = static::uidFor($user);
            $token = $this->firebase->auth()->createCustomToken($uid, [], 3600);

            return [
                'token' => $token->toString(),
                'uid' => $uid,
                'database_url' => config('delivery.firebase.database_url'),
                'expires_in' => 3600,
            ];
        } catch (Throwable $e) {
            Log::warning("[delivery] could not mint tracking token for user #{$user->id}: ".$e->getMessage());

            return null;
        }
    }
}
