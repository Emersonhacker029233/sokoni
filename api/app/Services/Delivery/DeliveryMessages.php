<?php

namespace App\Services\Delivery;

use App\Models\DeliveryJob;
use App\Models\User;

/**
 * Who hears about each delivery step, and what they read, in their own
 * language (lang/{en,sw}/delivery.php). Every message lands in the in-app
 * inbox and, when Firebase is configured, as a push to the Sokoni app.
 */
class DeliveryMessages
{
    public function __construct(private readonly DeliveryNotifier $notifier) {}

    public function statusChanged(DeliveryJob $job, string $from, string $to): void
    {
        $job->loadMissing(['order.buyer', 'order.seller.user']);
        $order = $job->order;
        $data = ['type' => 'delivery_status', 'order_id' => $order->id, 'job_id' => $job->id, 'status' => $to];

        $paymentTimeout = $to === 'cancelled' && $job->cancel_reason === DeliveryJobService::PAYMENT_TIMEOUT;
        $notServiced = $to === 'no_driver_found' && $job->failure_reason === 'area_not_serviced';

        $buyerKey = match (true) {
            $paymentTimeout => 'buyer_payment_expired',
            $to === 'pending_payment' => 'buyer_pay_fee',
            $to === 'awaiting_ready' => 'buyer_fee_paid',
            $to === 'pending_driver' && in_array($from, ['driver_assigned', 'arrived_at_shop'], true) => 'buyer_driver_changed',
            $to === 'pending_driver' => 'buyer_finding_driver',
            $notServiced => 'buyer_not_serviced',
            in_array($to, ['driver_assigned', 'arrived_at_shop', 'on_the_way', 'delivered', 'no_driver_found', 'failed', 'cancelled'], true) => "buyer_{$to}",
            default => null,
        };
        if ($buyerKey !== null && $order->buyer !== null) {
            $this->send($order->buyer, $buyerKey, $job, $data);
        }

        $sellerKey = match (true) {
            $paymentTimeout => 'seller_payment_expired',
            $to === 'awaiting_ready' => 'seller_fee_paid',
            $to === 'driver_assigned' => 'seller_driver_assigned',
            $to === 'arrived_at_shop' => 'seller_driver_at_shop',
            $to === 'on_the_way' => 'seller_picked_up',
            $to === 'delivered' => 'seller_delivered',
            $to === 'no_driver_found' && $from !== 'pending_quote' => 'seller_no_driver',
            $to === 'failed' => 'seller_failed',
            // Not when the seller cancelled it themselves.
            $to === 'cancelled' && $job->cancelled_by !== 'seller' => 'seller_cancelled',
            default => null,
        };
        if ($sellerKey !== null && $order->seller?->user !== null) {
            $this->send($order->seller->user, $sellerKey, $job, $data);
        }
    }

    /** A delivery-fee payment attempt failed or timed out; the buyer can try again. */
    public function paymentFailed(DeliveryJob $job): void
    {
        $job->loadMissing(['order.buyer', 'order.seller']);
        if ($job->order->buyer === null) {
            return;
        }
        $this->send($job->order->buyer, 'buyer_payment_failed', $job, [
            'type' => 'delivery_status', 'order_id' => $job->order_id, 'job_id' => $job->id, 'status' => $job->status,
        ]);
    }

    private function send(User $user, string $key, DeliveryJob $job, array $data): void
    {
        // Same Settings > Notifications "orders" toggle the existing order pushes honour.
        if (! $user->notify_orders) {
            return;
        }

        $locale = $user->preferredLocale();
        $driver = (array) ($job->provider_driver ?? []);
        $replace = [
            'code' => $job->order->code,
            'fee' => number_format((int) $job->fee),
            'shop' => $job->order->seller?->shop_name ?? '',
            'driver' => $driver['name'] ?? __('delivery.your_driver', [], $locale),
            'plate' => $driver['plate'] ?? '',
        ];

        $this->notifier->send(
            $user,
            __("delivery.push.{$key}.title", $replace, $locale),
            __("delivery.push.{$key}.body", $replace, $locale),
            $data,
        );
    }
}
