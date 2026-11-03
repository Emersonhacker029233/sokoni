<?php

namespace App\Services\Delivery;

use App\Models\DriverProfile;
use App\Services\Sms\SmsGateway;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Tells a driver about an admin decision on their account, in their own
 * language (lang/{en,sw}/delivery.php `account.*`):
 *
 *  1. always an in-app notification record (app_notifications),
 *  2. a push to the Sokoni Driver app when one can really arrive,
 *  3. otherwise an SMS through the configured SMS driver.
 *
 * A failed SMS or push is logged and never undoes the decision itself.
 */
class DriverAccountMessages
{
    public const EVENTS = ['approved', 'vehicle_approved', 'rejected', 'suspended', 'reinstated'];

    public function __construct(
        private readonly DeliveryNotifier $push,
        private readonly SmsGateway $sms,
    ) {}

    /**
     * @return list<string> the channels used, for the activity log
     */
    public function send(DriverProfile $driver, string $event, ?string $reason = null): array
    {
        $driver->loadMissing(['user', 'activeVehicle']);
        $user = $driver->user;
        $locale = $user->preferredLocale();
        $replace = [
            'reason' => $reason ?? '',
            'plate' => $driver->activeVehicle?->plate_number ?? '',
        ];
        $title = __("delivery.account.{$event}.title", $replace, $locale);
        $body = __("delivery.account.{$event}.body", $replace, $locale);
        $data = ['type' => 'driver_account', 'status' => $event];
        $channels = [];

        try {
            $user->appNotifications()->create(['title' => $title, 'body' => $body, 'data' => $data]);
            $channels[] = 'in_app';
        } catch (Throwable $e) {
            Log::warning("[driver-review] in-app notification for driver #{$driver->id} failed: ".$e->getMessage());
        }

        if ($this->push->canReach($user, DeliveryNotifier::APP_DRIVER)) {
            $this->push->send($user, DeliveryNotifier::APP_DRIVER, $title, $body, $data);
            $channels[] = 'push';
        } elseif ($user->phone !== null) {
            $sms = __("delivery.account.{$event}.sms", [
                ...$replace,
                // Keeps a long admin reason from turning into a three-part SMS.
                'reason' => Str::limit($reason ?? '', 100),
            ], $locale);
            try {
                $this->sms->sendMessage($user->phone, $sms);
                $channels[] = 'sms';
            } catch (Throwable $e) {
                Log::warning("[driver-review] SMS to driver #{$driver->id} failed: ".$e->getMessage());
            }
        }

        return $channels;
    }
}
