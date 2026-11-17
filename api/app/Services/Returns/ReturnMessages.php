<?php

namespace App\Services\Returns;

use App\Models\ReturnRequest;
use App\Models\User;
use App\Services\Delivery\DeliveryNotifier;
use App\Services\Moderation\BlockList;

/**
 * Both sides hear about every step of a return (in-app inbox + push), in
 * their own language. A shop that has blocked the buyer hears nothing
 * from that buyer: the request goes to Sokoni support instead.
 */
class ReturnMessages
{
    public function __construct(
        private readonly DeliveryNotifier $notifier,
        private readonly BlockList $blocks,
    ) {}

    public function toBuyer(ReturnRequest $return, string $key, array $extra = []): void
    {
        $return->loadMissing('buyer');
        if ($return->buyer !== null) {
            $this->send($return->buyer, "buyer_{$key}", $return, $extra);
        }
    }

    public function toSeller(ReturnRequest $return, string $key, array $extra = []): void
    {
        $return->loadMissing('shop.user');
        $seller = $return->shop?->user;
        if ($seller === null || $this->blocks->hasBlocked($seller, $return->buyer_id)) {
            return;
        }
        $this->send($seller, "seller_{$key}", $return, $extra);
    }

    private function send(User $user, string $key, ReturnRequest $return, array $extra): void
    {
        if (! $user->notify_orders) {
            return;
        }

        $return->loadMissing(['order', 'shop']);
        $locale = $user->preferredLocale();
        $driver = (array) ($return->provider_driver ?? []);
        $replace = [
            'code' => $return->order->code,
            'shop' => $return->shop?->shop_name ?? '',
            'reason' => __("returns.reasons.{$return->reason}", [], $locale),
            'fee' => number_format((int) $return->fee),
            'driver' => $driver['name'] ?? __('delivery.your_driver', [], $locale),
            'plate' => $driver['plate'] ?? '',
            'note' => '',
            ...$extra,
        ];

        $this->notifier->send(
            $user,
            __("returns.push.{$key}.title", $replace, $locale),
            __("returns.push.{$key}.body", $replace, $locale),
            ['type' => 'return_status', 'order_id' => $return->order_id, 'return_id' => $return->id, 'status' => $return->status],
        );
    }
}
