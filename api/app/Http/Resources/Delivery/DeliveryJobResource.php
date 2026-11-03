<?php

namespace App\Http\Resources\Delivery;

use App\Models\DeliveryJob;
use App\Models\Payment;
use App\Services\Delivery\DeliveryPaymentService;
use App\Support\DeliverySettings;
use App\Support\PrivateFileUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One delivery job, shaped for whoever is looking at it.
 *
 * Privacy rules (client-approved 2026-10-29):
 *  - phone numbers only while the job is active (assigned through arrived),
 *  - live location only for the buyer, only while active,
 *  - the delivery PIN only for the buyer,
 *  - the seller never sees the PIN or the driver's location.
 *
 * @mixin DeliveryJob
 */
class DeliveryJobResource extends JsonResource
{
    public const BUYER = 'buyer';

    public const SELLER = 'seller';

    public const DRIVER = 'driver';

    private string $viewer = self::BUYER;

    public static function for(DeliveryJob $job, string $viewer): self
    {
        $resource = new self($job);
        $resource->viewer = $viewer;

        return $resource;
    }

    public function toArray(Request $request): array
    {
        $this->resource->loadMissing(['order.seller.user', 'order.buyer', 'order.items', 'driver.user', 'vehicle', 'events', 'ratings', 'payments']);

        $base = [
            'id' => $this->id,
            'order_id' => $this->order_id,
            'order_code' => $this->orderCodeForViewer(),
            'status' => $this->status,
            'is_active' => $this->isDriverActive(),
            'distance_km' => $this->distance_km,
            'currency' => 'TZS',
            'timeline' => $this->timeline(),
            'cancelled_by' => $this->cancelled_by,
            'cancel_reason' => $this->cancel_reason,
            'failure_reason' => $this->failure_reason,
            'created_at' => $this->created_at,
        ];

        return match ($this->viewer) {
            self::DRIVER => [...$base, ...$this->driverView()],
            self::SELLER => [...$base, ...$this->sellerView()],
            default => [...$base, ...$this->buyerView()],
        };
    }

    private function buyerView(): array
    {
        $active = $this->isDriverActive();
        $hasDriver = $this->driver !== null && in_array($this->status, [...DeliveryJob::DRIVER_ACTIVE_STATUSES, 'delivered'], true);
        $reassignable = in_array($this->status, ['no_driver_found', 'failed'], true) && $this->failure_reason !== 'customer_unreachable';

        return [
            'fee' => $this->fee,
            'payment_status' => $this->payment_status,
            'amount_due' => $this->amountDue(),
            'pay_cash_to_driver' => $this->payment_status === 'cash',
            'delivery_pin' => ! $this->isTerminal() && $this->status !== 'pending_payment' ? $this->delivery_pin : null,
            'pin_locked' => $this->pin_locked_at !== null,
            'dispatch_wave' => $this->status === 'pending_driver' ? $this->dispatch_wave : null,
            'pickup' => [
                'shop_name' => $this->order->seller?->shop_name,
                'lat' => $this->pickup_lat,
                'lng' => $this->pickup_lng,
            ],
            'dropoff' => ['lat' => $this->dropoff_lat, 'lng' => $this->dropoff_lng, 'address' => $this->dropoff_address, 'phone' => $this->dropoffPhone()],
            'payment' => $this->paymentView(),
            'driver' => $hasDriver ? [
                'name' => $this->driver->user->name,
                'photo_url' => PrivateFileUrl::for($this->driver->photo),
                'rating_avg' => $this->driver->rating_avg,
                'rating_count' => $this->driver->rating_count,
                'phone' => $active ? $this->driver->user->phone : null,
                'vehicle' => $this->vehicleArray(),
            ] : null,
            'driver_location' => $active && $this->driver?->last_lat !== null ? [
                'lat' => $this->driver->last_lat,
                'lng' => $this->driver->last_lng,
                'at' => $this->driver->last_located_at,
            ] : null,
            'tracking' => $active ? ['channel' => "tracking/{$this->id}"] : null,
            'refund' => $this->refund_amount ? ['amount' => $this->refund_amount, 'status' => $this->refund_status] : null,
            'actions' => [
                'can_pay' => $this->amountDue() > 0 && app(DeliveryPaymentService::class)->onlinePaymentAvailable(),
                'can_cancel' => ! $this->isTerminal() && ! $this->isInTransit() && ! ($this->status === 'failed' && ! $reassignable),
                'can_retry' => $reassignable,
                'can_switch_to_pickup' => in_array($this->status, ['pending_payment', 'pending_driver', 'no_driver_found'], true) || ($this->status === 'failed' && $reassignable),
                'can_rate' => $this->status === 'delivered' && ! $this->ratings->contains('direction', 'buyer_to_driver'),
            ],
        ];
    }

    private function sellerView(): array
    {
        $atShopPhase = in_array($this->status, ['driver_assigned', 'arrived_at_shop'], true);

        return [
            'driver' => $this->driver !== null && $this->isDriverActive() ? [
                'name' => $this->driver->user->name,
                'photo_url' => PrivateFileUrl::for($this->driver->photo),
                'phone' => $atShopPhase ? $this->driver->user->phone : null,
                'vehicle' => $this->vehicleArray(),
            ] : null,
            // The seller hands over only after the driver types these 4 characters.
            'pickup_code' => $atShopPhase ? $this->pickupCode() : null,
        ];
    }

    private function driverView(): array
    {
        $order = $this->order;
        $active = $this->isDriverActive();
        $beforePickup = in_array($this->status, ['driver_assigned', 'arrived_at_shop'], true);
        $waitMinutes = (int) config('delivery.unreachable_wait_minutes');

        return [
            'fee' => $this->fee,
            'commission' => $this->commission,
            'driver_earnings' => $this->driver_earnings,
            'earned_amount' => $this->earned_amount,
            'payment_status' => $this->payment_status,
            'collect_cash' => $this->payment_status === 'cash' ? $this->fee : 0,
            'shop' => [
                'name' => $order->seller?->shop_name,
                'address' => $this->pickup_address,
                'lat' => $this->pickup_lat,
                'lng' => $this->pickup_lng,
                'phone' => $beforePickup ? ($order->seller?->user?->phone ?? $order->seller?->whatsapp) : null,
            ],
            'customer' => [
                'name' => $order->buyer?->name,
                'phone' => $active ? $this->dropoffPhone() : null,
                'address' => $this->dropoff_address,
                'lat' => $this->dropoff_lat,
                'lng' => $this->dropoff_lng,
            ],
            'items' => $order->items->map(fn ($i) => ['title' => $i->title_snapshot, 'qty' => $i->qty])->values(),
            'notes' => $order->notes,
            'call_attempts' => $this->call_attempts,
            'pin_attempts_left' => max(0, (int) config('delivery.pin_max_attempts') - $this->pin_attempts),
            'pin_locked' => $this->pin_locked_at !== null,
            'proof_photo_required' => DeliverySettings::proofPhotoRequired(),
            'unreachable_available_at' => $this->status === 'arrived' && $this->arrived_at ? $this->arrived_at->copy()->addMinutes($waitMinutes) : null,
            'actions' => [
                'next' => array_values(array_intersect(
                    DeliveryJob::TRANSITIONS[$this->status] ?? [],
                    ['arrived_at_shop', 'picked_up', 'on_the_way', 'arrived', 'delivered', 'failed'],
                )),
                'can_cancel' => $beforePickup,
                'can_rate' => $this->status === 'delivered' && ! $this->ratings->contains('direction', 'driver_to_buyer'),
            ],
        ];
    }

    /**
     * The pickup code is the last 4 characters of the order code, so a
     * driver who could read the full code before pickup could "confirm"
     * a pickup without ever meeting the seller. Drivers see it masked
     * until the goods are in their hands.
     */
    private function orderCodeForViewer(): string
    {
        $code = (string) $this->order->code;
        if ($this->viewer !== self::DRIVER || ! in_array($this->status, ['pending_driver', 'driver_assigned', 'arrived_at_shop'], true)) {
            return $code;
        }

        return substr($code, 0, -4).'••••';
    }

    /** What "Pay now" would charge right now: the fee frozen at acceptance, only while it is still owed. */
    private function amountDue(): int
    {
        return $this->payment_status === 'unpaid' && $this->status === 'pending_payment' ? (int) $this->fee : 0;
    }

    /** Who the driver calls at the drop-off: the phone given at checkout (Sokoni 1.1), else the buyer's own. */
    private function dropoffPhone(): ?string
    {
        return $this->order->delivery_phone ?: $this->order->buyer?->phone;
    }

    /**
     * Everything the buyer's payment card shows. Every amount is a stored
     * number: the order's goods subtotal, the fee frozen on this job, and
     * the amount of a recorded payment. The app adds nothing up itself
     * except goods + delivery for the summary line, which is also returned.
     *
     * While the wallet is off (WALLET_PLAN.md section 4) "Pay now" covers
     * the delivery fee only and the goods are paid to the shop directly.
     */
    private function paymentView(): array
    {
        $payments = app(DeliveryPaymentService::class);
        $capabilities = $payments->capabilities();
        $latest = $this->payments->sortByDesc('id')->first();
        $paid = $this->payments->where('status', 'succeeded')->sortByDesc('id')->first();
        $goods = (int) $this->order->subtotal;

        return [
            'mode' => $this->payment_status === 'cash' ? 'cash' : 'online',
            'status' => $this->payment_status,
            'amount_due' => $this->amountDue(),
            'covers' => ['delivery_fee'],
            'goods_paid_to' => 'shop',
            'goods_total' => $goods,
            'delivery_fee' => (int) $this->fee,
            'total' => $goods + (int) $this->fee,
            'deadline' => $this->status === 'pending_payment'
                ? $this->created_at->copy()->addMinutes(DeliverySettings::unpaidTimeoutMinutes())
                : null,
            'online_available' => $payments->onlinePaymentAvailable(),
            'sandbox' => $capabilities->sandbox,
            'channels' => $capabilities->channels,
            'attempt' => $latest instanceof Payment ? $this->attemptArray($latest) : null,
            'receipt' => $paid instanceof Payment ? [
                'amount' => (int) $paid->amount,
                'channel' => $paid->channel,
                'reference' => $paid->provider_reference,
                'paid_at' => $paid->paid_at,
            ] : null,
        ];
    }

    private function attemptArray(Payment $payment): array
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

    private function vehicleArray(): ?array
    {
        return $this->vehicle ? [
            'type' => $this->vehicle->type,
            'plate_number' => $this->vehicle->plate_number,
            'make' => $this->vehicle->make,
            'colour' => $this->vehicle->colour,
        ] : null;
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
