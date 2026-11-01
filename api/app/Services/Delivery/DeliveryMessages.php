<?php

namespace App\Services\Delivery;

use App\Models\DeliveryJob;
use App\Models\User;

/**
 * Who hears about each delivery event, and what they read, in their own
 * language (lang/{en,sw}/delivery.php). The buyer/seller go to the Sokoni
 * app, the driver to Sokoni Driver.
 */
class DeliveryMessages
{
    public function __construct(private readonly DeliveryNotifier $notifier) {}

    public function statusChanged(DeliveryJob $job, string $from, string $to): void
    {
        $job->loadMissing(['order.buyer', 'order.seller.user', 'driver.user']);
        $order = $job->order;
        $data = ['type' => 'delivery_status', 'order_id' => $order->id, 'job_id' => $job->id, 'status' => $to];

        $paymentTimeout = $to === 'cancelled' && $job->cancel_reason === DeliveryJobService::PAYMENT_TIMEOUT;

        $buyerKey = match (true) {
            $paymentTimeout => 'buyer_payment_expired',
            $to === 'pending_driver' && in_array($from, ['driver_assigned', 'arrived_at_shop'], true) => 'buyer_driver_cancelled',
            $to === 'pending_driver' => 'buyer_finding_driver',
            in_array($to, ['driver_assigned', 'arrived_at_shop', 'picked_up', 'on_the_way', 'arrived', 'delivered', 'no_driver_found', 'failed', 'cancelled'], true) => "buyer_{$to}",
            default => null,
        };
        if ($buyerKey !== null) {
            $this->toCustomerApp($order->buyer, $buyerKey, $job, $data);
        }

        $sellerKey = match (true) {
            $paymentTimeout => 'seller_payment_expired',
            $to === 'driver_assigned' => 'seller_driver_assigned',
            $to === 'arrived_at_shop' => 'seller_driver_at_shop',
            $to === 'delivered' => 'seller_delivered',
            $to === 'failed' => 'seller_failed',
            default => null,
        };
        if ($sellerKey !== null && $order->seller?->user !== null) {
            $this->toCustomerApp($order->seller->user, $sellerKey, $job, $data);
        }

        // The driver triggered most transitions themselves, so they only
        // hear about the ones somebody else caused.
        if ($to === 'cancelled' && $job->driver !== null) {
            $this->toDriverApp($job->driver->user, 'driver_job_cancelled', $job, $data);
        }
    }

    public function jobCreated(DeliveryJob $job): void
    {
        $job->loadMissing('order.buyer');
        $key = $job->status === 'pending_payment' ? 'buyer_pay_fee' : 'buyer_finding_driver';
        $this->toCustomerApp($job->order->buyer, $key, $job, [
            'type' => 'delivery_status', 'order_id' => $job->order_id, 'job_id' => $job->id, 'status' => $job->status,
        ]);
    }

    public function offer(User $driver, DeliveryJob $job, float $distanceKm): void
    {
        $job->loadMissing('order.seller');
        $this->toDriverApp($driver, 'driver_new_offer', $job, [
            'type' => 'delivery_offer',
            'job_id' => $job->id,
            'earnings' => $job->driver_earnings,
            'distance_km' => round($distanceKm, 1),
        ]);
    }

    public function offerWithdrawn(User $driver, DeliveryJob $job): void
    {
        $this->notifier->send($driver, DeliveryNotifier::APP_DRIVER, '', '', [
            'type' => 'delivery_offer_withdrawn', 'job_id' => $job->id,
        ], silent: true);
    }

    public function pinLocked(DeliveryJob $job): void
    {
        $job->loadMissing('order.buyer');
        $this->toCustomerApp($job->order->buyer, 'buyer_pin_locked', $job, [
            'type' => 'delivery_status', 'order_id' => $job->order_id, 'job_id' => $job->id, 'status' => $job->status,
        ]);
    }

    private function toCustomerApp(User $user, string $key, DeliveryJob $job, array $data): void
    {
        // Same Settings > Notifications "orders" toggle the existing order pushes honour.
        if (! $user->notify_orders) {
            return;
        }
        $this->send($user, DeliveryNotifier::APP_CUSTOMER, $key, $job, $data);
    }

    private function toDriverApp(User $user, string $key, DeliveryJob $job, array $data): void
    {
        $this->send($user, DeliveryNotifier::APP_DRIVER, $key, $job, $data);
    }

    private function send(User $user, string $app, string $key, DeliveryJob $job, array $data): void
    {
        $locale = $user->preferredLocale();
        $replace = [
            'code' => $job->order->code,
            'fee' => number_format($job->fee),
            'earnings' => number_format($job->driver_earnings),
            'shop' => $job->order->seller?->shop_name ?? '',
            'driver' => $job->driver?->user?->name ?? '',
        ];

        $this->notifier->send(
            $user,
            $app,
            __("delivery.push.{$key}.title", $replace, $locale),
            __("delivery.push.{$key}.body", $replace, $locale),
            $data,
        );
    }
}
