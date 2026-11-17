<?php

namespace App\Services\Delivery;

use App\Models\Device;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Messaging\CloudMessage;
use Throwable;

/**
 * Real FCM via kreait/firebase-php. A push failure is logged and swallowed.
 * It must never roll back or 500 a status change, because every update is
 * also in the inbox and readable by polling the API.
 */
class FcmDeliveryNotifier extends BaseDeliveryNotifier
{
    public function __construct(private readonly FirebaseConnection $firebase) {}

    protected function deliver(User $user, Collection $tokens, string $title, string $body, array $data): void
    {
        try {
            $message = CloudMessage::new()
                ->withData($data)
                ->withNotification(['title' => $title, 'body' => $body])
                ->withAndroidConfig(['priority' => 'high'])
                ->withApnsConfig(['headers' => ['apns-priority' => '10'], 'payload' => ['aps' => ['sound' => 'default']]]);

            $report = $this->firebase->messaging()->sendMulticast($message, $tokens->values()->all());

            $dead = array_merge($report->invalidTokens(), $report->unknownTokens());
            if ($dead !== []) {
                Device::query()->whereIn('fcm_token', $dead)->delete();
            }
        } catch (Throwable $e) {
            Log::warning("[delivery] FCM send to user #{$user->id} failed: ".$e->getMessage());
        }
    }
}
