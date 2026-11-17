<?php

namespace App\Http\Resources\Delivery;

use App\Http\Resources\Returns\ReturnRequestResource;
use App\Models\DeliveryJob;
use App\Models\Payment;
use App\Models\ReturnRequest;
use App\Services\Delivery\Bolt\BoltStates;
use App\Services\Delivery\DeliveryPaymentService;
use App\Services\Returns\ReturnService;
use App\Support\DeliveryAccess;
use App\Support\DeliverySettings;
use App\Support\ReturnPolicy;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One Bolt delivery, shaped for whoever is looking at it
 * (GET /api/delivery/orders/{order}).
 *
 * Privacy rules:
 *  - the Bolt driver's phone only while a driver is on the job (buyer),
 *    and only until pickup (seller),
 *  - the tracking link only while the ride is running,
 *  - the seller never sees the fee, the drop-off or the buyer's phone.
 *
 * @mixin DeliveryJob
 */
class DeliveryJobResource extends JsonResource
{
    public const BUYER = 'buyer';

    public const SELLER = 'seller';

    private string $viewer = self::BUYER;

    public static function for(DeliveryJob $job, string $viewer): self
    {
        $resource = new self($job);
        $resource->viewer = $viewer;

        return $resource;
    }

    public function toArray(Request $request): array
    {
        $this->resource->loadMissing(['order.seller.user', 'order.buyer', 'events', 'payments']);

        $base = [
            'id' => $this->id,
            'order_id' => $this->order_id,
            'order_code' => (string) $this->order->code,
            'provider' => $this->provider ?? 'bolt',
            'status' => $this->status,
            'is_active' => $this->isDriverActive(),
            'distance_km' => $this->distance_km,
            'currency' => $this->currency ?? 'TZS',
            'timeline' => $this->timeline(),
            'cancelled_by' => $this->cancelled_by,
            'cancel_reason' => $this->cancel_reason,
            'failure_reason' => $this->failure_reason,
            'created_at' => $this->created_at,
        ];

        $view = $this->viewer === self::SELLER
            ? [...$base, ...$this->sellerView()]
            : [...$base, ...$this->buyerView()];

        if (DeliveryAccess::returnsEnabledFor($request->user())) {
            $view['return'] = $this->returnView($request);
        }

        return $view;
    }

    /**
     * Returns (docs/delivery/RETURNS.md), on the order screen: the policy the
     * buyer saw at checkout, until when a return can be requested, and the
     * request itself once there is one.
     */
    private function returnView(Request $request): array
    {
        $order = $this->order;
        $existing = ReturnRequest::query()->where('order_id', $order->id)->first();
        $returns = app(ReturnService::class);

        return [
            'policy' => ReturnPolicy::toArray($order->return_policy, $order->seller?->return_notes),
            'eligible_until' => ReturnPolicy::deadline($order->return_policy, $this->delivered_at),
            'can_request' => $this->viewer === self::BUYER && $existing === null
                && $returns->refusal($order, $request->user()) === null,
            'request' => $existing === null ? null : ReturnRequestResource::for(
                $existing,
                $this->viewer === self::SELLER ? ReturnRequestResource::SELLER : ReturnRequestResource::BUYER,
            )->resolve($request),
        ];
    }

    private function buyerView(): array
    {
        $atShop = $this->parcelAtShop();
        $retryable = in_array($this->status, ['no_driver_found', 'failed'], true) && BoltStates::retryable($this->failure_reason);

        return [
            'fee' => (int) $this->fee,
            'estimate' => $this->estimate_max === null ? null : [
                'min' => $this->estimate_min,
                'max' => $this->estimate_max,
                'category' => $this->provider_category,
            ],
            'payment_status' => $this->payment_status,
            'amount_due' => $this->amountDue(),
            'pickup' => [
                'shop_name' => $this->order->seller?->shop_name,
                'lat' => $this->pickup_lat,
                'lng' => $this->pickup_lng,
            ],
            'dropoff' => ['lat' => $this->dropoff_lat, 'lng' => $this->dropoff_lng, 'address' => $this->dropoff_address, 'phone' => $this->dropoffPhone()],
            'payment' => $this->paymentView(),
            'driver' => $this->driverView(phone: $this->isDriverActive()),
            'tracking_url' => $this->trackingUrl(),
            'refund' => $this->refund_amount ? ['amount' => (int) $this->refund_amount, 'status' => $this->refund_status] : null,
            'actions' => [
                'can_pay' => $this->amountDue() > 0 && app(DeliveryPaymentService::class)->onlinePaymentAvailable(),
                'can_cancel' => ! $this->isTerminal() && $atShop,
                'can_retry' => $retryable,
                'can_switch_to_pickup' => ! $this->isTerminal() && $atShop
                    && in_array($this->status, ['pending_quote', 'pending_payment', 'awaiting_ready', 'pending_driver', 'no_driver_found', 'failed'], true),
            ],
        ];
    }

    private function sellerView(): array
    {
        $beforePickup = in_array($this->status, ['pending_driver', 'driver_assigned', 'arrived_at_shop'], true);

        return [
            // What the shop needs to know to act: has the buyer paid, and is it time to mark the order ready?
            'fee_paid' => $this->payment_status === 'paid',
            'driver' => $this->driverView(phone: in_array($this->status, ['driver_assigned', 'arrived_at_shop'], true)),
            'tracking_url' => $beforePickup ? $this->trackingUrl() : null,
        ];
    }

    /** Bolt's driver: name, plate, model, photo; phone only when $phone. */
    private function driverView(bool $phone): ?array
    {
        $driver = (array) ($this->provider_driver ?? []);
        $visible = in_array($this->status, [...DeliveryJob::DRIVER_ACTIVE_STATUSES, 'delivered'], true);
        if (! $visible || ($driver['name'] ?? null) === null && ($driver['plate'] ?? null) === null) {
            return null;
        }

        return [
            'name' => $driver['name'] ?? null,
            'phone' => $phone ? ($driver['phone'] ?? null) : null,
            'plate' => $driver['plate'] ?? null,
            'model' => $driver['model'] ?? null,
            'photo_url' => $driver['photo_url'] ?? null,
            'eta_seconds' => $this->isDriverActive() ? ($driver['eta_seconds'] ?? null) : null,
        ];
    }

    private function trackingUrl(): ?string
    {
        return $this->isRideActive() ? (((array) ($this->provider_driver ?? []))['tracking_url'] ?? null) : null;
    }

    /** What "Pay now" would charge right now: the fee frozen when the shop accepted, only while it is owed. */
    private function amountDue(): int
    {
        return $this->payment_status === 'unpaid' && $this->status === 'pending_payment' ? (int) $this->fee : 0;
    }

    /** Who Bolt's driver hands the parcel to: the phone given at checkout, else the buyer's own. */
    private function dropoffPhone(): ?string
    {
        return $this->order->delivery_phone ?: $this->order->buyer?->phone;
    }

    /**
     * The buyer's payment card. Every amount is a stored number: the
     * order's goods subtotal, the fee frozen on this job, the amount of a
     * recorded payment. Goods are paid to the shop directly; "Pay now"
     * covers the delivery fee only.
     */
    private function paymentView(): array
    {
        $payments = app(DeliveryPaymentService::class);
        $capabilities = $payments->capabilities();
        $latest = $this->payments->sortByDesc('id')->first();
        $paid = $this->payments->where('status', 'succeeded')->sortByDesc('id')->first();
        $goods = (int) $this->order->subtotal;

        return [
            'mode' => 'online',
            'status' => $this->payment_status,
            'amount_due' => $this->amountDue(),
            'covers' => ['delivery_fee'],
            'goods_paid_to' => 'shop',
            'goods_total' => $goods,
            'delivery_fee' => (int) $this->fee,
            'total' => $goods + (int) $this->fee,
            'deadline' => $this->status === 'pending_payment'
                ? $this->paymentWindowStart()->copy()->addMinutes(DeliverySettings::unpaidTimeoutMinutes())
                : null,
            'online_available' => $payments->onlinePaymentAvailable(),
            'sandbox' => $capabilities->sandbox,
            'channels' => $capabilities->channels,
            'attempt' => $latest instanceof Payment ? self::attemptArray($latest) : null,
            'receipt' => $paid instanceof Payment ? [
                'amount' => (int) $paid->amount,
                'channel' => $paid->channel,
                'reference' => $paid->provider_reference,
                'paid_at' => $paid->paid_at,
            ] : null,
        ];
    }

    public static function attemptArray(Payment $payment): array
    {
        $timeout = max(10, (int) config('payments.attempt_timeout_seconds'));

        return [
            'id' => $payment->id,
            'status' => $payment->status,
            'amount' => (int) $payment->amount,
            'channel' => $payment->channel,
            'msisdn' => self::maskPhone((string) $payment->msisdn),
            'failure_reason' => $payment->failure_reason,
            'created_at' => $payment->created_at,
            'expires_at' => $payment->status === 'pending' ? $payment->created_at->copy()->addSeconds($timeout) : null,
        ];
    }

    /** +255754123456 -> +25575••••456: enough for the buyer to recognise their own number. */
    public static function maskPhone(string $phone): string
    {
        if (strlen($phone) < 9) {
            return $phone;
        }

        return substr($phone, 0, 6).str_repeat('•', strlen($phone) - 9).substr($phone, -3);
    }

    private function timeline(): array
    {
        return $this->events
            ->where('type', 'status')
            ->map(fn ($e) => ['status' => $e->to_status, 'at' => $e->created_at, 'by' => $e->actor_role, 'reason' => $e->meta['reason'] ?? null])
            ->values()
            ->all();
    }
}
