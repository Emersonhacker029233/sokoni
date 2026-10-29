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
 * also readable by polling the API.
 */
class FcmDeliveryNotifier extends BaseDeliveryNotifier
{
    public function __construct(private readonly FirebaseConnection $firebase) {}

    protected function deliver(User $user, Collection $tokens, string $title, string $body, array $data, bool $silent): void
    {
        try {
            $apns = $silent
                ? ['headers' => ['apns-priority' => '5', 'apns-push-type' => 'background'], 'payload' => ['aps' => ['content-available' => 1]]]
                : ['headers' => ['apns-priority' => '10'], 'payload' => ['aps' => ['sound' => 'default']]];

            $message = CloudMessage::new()
                ->withData($data)
                ->withAndroidConfig(['priority' => 'high'])
                ->withApnsConfig($apns);

            if (! $silent) {
                $message = $message->withNotification(['title' => $title, 'body' => $body]);
            }

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
