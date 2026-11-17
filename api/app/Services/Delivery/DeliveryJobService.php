<?php

namespace App\Services\Delivery;

use App\Models\DeliveryJob;
use App\Models\Order;
use App\Models\User;
use App\Services\Delivery\Bolt\BoltApiException;
use App\Services\Delivery\Bolt\BoltRideBooker;
use App\Services\Delivery\Bolt\BoltRideDetails;
use App\Services\Delivery\Bolt\BoltStates;
use App\Support\DeliverySettings;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * A Bolt delivery from the shop's acceptance to COMPLETED
 * (docs/delivery/BOLT.md):
 *
 *   shop accepts     -> job priced from Bolt's estimate (fee = upper bound)
 *   buyer pays fee   -> awaiting_ready
 *   shop marks ready -> Bolt ride booked at once with a fresh fare_id
 *   Bolt states      -> polled every 10 s (and the webhook) -> statuses
 *   COMPLETED        -> delivered, order completed, receipt fetched
 *
 * The buyer pays the goods to the shop directly, as before; only the
 * delivery fee goes through Sokoni. Order rows are written with
 * saveQuietly() so the order observer never re-enters while the delivery
 * side is the one changing the order.
 */
class DeliveryJobService
{
    /** Timeline tag when a seller cancels after Bolt assigned a driver. */
    public const SELLER_CANCELLED_AFTER_ASSIGN = 'seller_cancelled_after_assign';

    /** cancel_reason for jobs whose delivery fee was not paid in time. */
    public const PAYMENT_TIMEOUT = 'payment_timeout';

    public function __construct(
        private readonly DeliveryStateMachine $stateMachine,
        private readonly BoltRideBooker $bolt,
    ) {}

    public static function enabled(): bool
    {
        return (bool) config('delivery.enabled');
    }

    /*
    |--------------------------------------------------------------------------
    | Creation and pricing
    |--------------------------------------------------------------------------
    */

    /**
     * The shop accepted a Bolt delivery order: snapshot both ends and ask
     * Bolt for the price. A failed quote never blocks the shop's tap.
     */
    public function createForAcceptedOrder(Order $order): ?DeliveryJob
    {
        if (! self::enabled() || ! $order->isDispatch() || $order->delivery_method !== 'delivery' || $order->deliveryJob()->exists()) {
            return null;
        }

        $order->loadMissing('seller');
        $seller = $order->seller;
        if ($seller?->lat === null || $seller?->lng === null || $order->delivery_lat === null || $order->delivery_lng === null) {
            Log::error("[delivery] order #{$order->id} accepted but shop or drop-off has no coordinates; no job created");

            return null;
        }

        $job = DB::transaction(function () use ($order, $seller) {
            $job = new DeliveryJob;
            $job->forceFill([
                'order_id' => $order->id,
                'status' => 'pending_quote',
                'provider' => DeliveryJob::PROVIDER_BOLT,
                'pickup_lat' => $seller->lat,
                'pickup_lng' => $seller->lng,
                'pickup_address' => $seller->address,
                'dropoff_lat' => $order->delivery_lat,
                'dropoff_lng' => $order->delivery_lng,
                'dropoff_address' => $order->address,
                'distance_km' => round(Haversine::km((float) $seller->lat, (float) $seller->lng, (float) $order->delivery_lat, (float) $order->delivery_lng), 2),
                'distance_source' => 'straight_line',
                // Own-driver pricing columns (NOT NULL, unused with Bolt).
                'base_fee' => 0,
                'per_km_fee' => 0,
                'fee' => 0,
                'commission_pct' => 0,
                'commission' => 0,
                'driver_earnings' => 0,
                'delivery_pin' => '',
                'payment_status' => 'unpaid',
                'currency' => 'TZS',
            ])->save();

            $this->stateMachine->record($job, null, 'system', 'status', null, 'pending_quote', meta: ['provider' => 'bolt']);

            return $job;
        });

        return $this->quote($job);
    }

    /**
     * Prices a job waiting in pending_quote: the cheapest Bolt category
     * with an ETA, shop -> customer. The buyer will pay the estimate's
     * upper bound; the difference to Bolt's receipt is kept for admins.
     */
    public function quote(DeliveryJob $job): DeliveryJob
    {
        $job = $job->fresh();
        if ($job->status !== 'pending_quote') {
            return $job;
        }

        try {
            $estimate = $this->bolt->quote($job->pickupPoint(), $job->dropoffPoint(), fresh: true);
        } catch (DeliveryUnavailableException $e) {
            return $this->giveUp($job, $e->reason, ['pending_quote']);
        } catch (BoltApiException $e) {
            return $this->postpone($job, $e, 'quote_failed');
        }

        return $this->stateMachine->transition($job, 'pending_payment', null, 'system', [
            'fee' => $estimate->customerFee(),
            'estimate_min' => $estimate->minAmount,
            'estimate_max' => $estimate->maxAmount,
            'currency' => $estimate->currency ?? 'TZS',
            'provider_category' => $estimate->category,
            'booking_attempts' => 0,
            'next_booking_at' => null,
            'failure_reason' => null,
        ], meta: ['estimate' => $estimate->toArray()], onlyFrom: ['pending_quote']);
    }

    /*
    |--------------------------------------------------------------------------
    | Payment and booking
    |--------------------------------------------------------------------------
    */

    /**
     * The delivery fee was confirmed by a VERIFIED server-side source
     * (gateway status query, or an admin recording a payment made outside
     * the app). Idempotent. Books Bolt at once if the shop is already ready.
     */
    public function markFeePaid(DeliveryJob $job, ?string $reference = null, ?User $admin = null): DeliveryJob
    {
        if ($job->fresh()->payment_status === 'paid') {
            return $job->fresh();
        }

        try {
            $job = $this->stateMachine->transition($job, 'awaiting_ready', $admin, $admin ? 'admin' : 'system',
                ['payment_status' => 'paid', 'paid_at' => now()],
                meta: array_filter(['reference' => $reference]),
                onlyFrom: ['pending_payment'],
            );
        } catch (DeliveryActionException $e) {
            $fresh = $job->fresh();
            if ($fresh->payment_status === 'paid') {
                return $fresh; // a concurrent confirmation won the row lock
            }
            if ($fresh->status === 'cancelled') {
                // Paid after the job was cancelled: keep the money traceable for a refund.
                DeliveryJob::query()->whereKey($job->id)->update([
                    'payment_status' => 'paid', 'paid_at' => now(),
                    'refund_amount' => $fresh->fee, 'refund_status' => 'pending',
                ]);

                return $fresh->fresh();
            }
            throw $e;
        }

        return $this->bookIfReady($job);
    }

    /** The shop marked the order ready for pickup through the existing order endpoint. */
    public function orderBecameReady(Order $order): void
    {
        $job = $order->deliveryJob()->first();
        if ($job !== null) {
            $this->bookIfReady($job);
        }
    }

    /**
     * Books the Bolt ride once BOTH the fee is paid and the shop has marked
     * the order ready, whichever happens last. A booking that is waiting
     * out a backoff is left to the minute cron.
     */
    public function bookIfReady(DeliveryJob $job): DeliveryJob
    {
        $job = $job->fresh(['order']);
        if ($job->status !== 'awaiting_ready' || ! $job->feeSettled() || $job->order?->status !== 'ready') {
            return $job;
        }
        if ($job->next_booking_at !== null && $job->next_booking_at->isFuture()) {
            return $job;
        }

        $lock = Cache::lock("delivery:book:{$job->id}", 60);
        if (! $lock->get()) {
            return $job; // another request is booking this job right now
        }

        try {
            return $this->book($job->fresh(['order.seller.user', 'order.buyer']));
        } finally {
            $lock->release();
        }
    }

    private function book(DeliveryJob $job): DeliveryJob
    {
        if ($job->status !== 'awaiting_ready') {
            return $job;
        }

        try {
            $booking = $this->bolt->book($job->pickupPoint(), $job->dropoffPoint(), $this->shopAsRider($job), $this->noteToDriver($job));
        } catch (DeliveryUnavailableException $e) {
            return $this->giveUp($job, $e->reason, ['awaiting_ready']);
        } catch (BoltApiException $e) {
            return $this->postpone($job, $e, 'booking_failed');
        }

        try {
            return $this->stateMachine->transition($job, 'pending_driver', null, 'system', [
                'provider_ref' => $booking['ride_id'],
                'provider_state' => 'SEARCHING',
                'provider_driver' => null,
                'provider_category' => $booking['estimate']->category,
                'booked_at' => now(),
                'last_polled_at' => null,
                'booking_attempts' => $job->booking_attempts + 1,
                'next_booking_at' => null,
                'failure_reason' => null,
            ], meta: ['ride_id' => $booking['ride_id'], 'booking_estimate' => $booking['estimate']->toArray()], onlyFrom: ['awaiting_ready']);
        } catch (DeliveryActionException $e) {
            // Cancelled while Bolt was booking: don't leave a driver driving to a cancelled order.
            Log::warning("[bolt] job #{$job->id} changed while booking; cancelling ride {$booking['ride_id']}");
            $this->cancelRide($job, $booking['ride_id']);
            throw $e;
        }
    }

    /**
     * Bolt's "rider" is the shop: the driver collects there. The customer's
     * name and phone go in the note, which Bolt shows the driver as soon as
     * they accept.
     *
     * @return array{name: string, phone: string}
     */
    private function shopAsRider(DeliveryJob $job): array
    {
        $shop = $job->order->seller;

        return [
            'name' => mb_substr((string) ($shop?->shop_name ?: 'Sokoni shop'), 0, 60),
            'phone' => (string) ($shop?->user?->phone ?: $shop?->whatsapp ?: ''),
        ];
    }

    private function noteToDriver(DeliveryJob $job): string
    {
        $order = $job->order;
        $customer = trim((string) $order->buyer?->name);
        $phone = $order->delivery_phone ?: $order->buyer?->phone;

        return mb_substr(sprintf(
            'Sokoni order %s. Collect parcel from shop, hand to customer: %s %s. Chukua mzigo dukani, mkabidhi mteja.',
            $order->code,
            $customer !== '' ? $customer : 'customer',
            $phone ?? '',
        ), 0, 250);
    }

    /**
     * A Bolt error that may go away: count it, wait 1, 2, 4, 8 ... minutes
     * (the minute cron picks it up), and give up after the configured
     * number of tries. Errors that will not go away (a malformed request,
     * a landline phone) give up at once.
     */
    private function postpone(DeliveryJob $job, BoltApiException $e, string $finalReason): DeliveryJob
    {
        $attempts = $job->booking_attempts + 1;
        $this->stateMachine->record($job, null, 'system', 'provider_error', note: $e->getMessage(), meta: [...$e->context(), 'attempt' => $attempts]);

        if ($e->isAccountProblem()) {
            Log::critical("[bolt] Bolt refused Sokoni's account on job #{$job->id}: check BOLT_CLIENT_ID/SECRET and the Bolt Business payment method", $e->context());
        }

        $max = max(1, (int) config('delivery.bolt.max_booking_attempts', 5));
        if ($attempts >= $max || (! $e->isTransient() && ! $e->isAccountProblem())) {
            return $this->giveUp($job, $finalReason, [$job->status], ['booking_attempts' => $attempts]);
        }

        DeliveryJob::query()->whereKey($job->id)->update([
            'booking_attempts' => $attempts,
            'next_booking_at' => now()->addMinutes(min(15, 2 ** ($attempts - 1))),
        ]);

        return $job->fresh();
    }

    /** Bolt can't take this delivery: the buyer is told and offered pickup or cancel (and booking again when it may help). */
    private function giveUp(DeliveryJob $job, string $reason, array $from, array $attributes = []): DeliveryJob
    {
        return $this->stateMachine->transition($job, 'no_driver_found', null, 'system',
            ['failure_reason' => $reason, 'next_booking_at' => null, ...$attributes],
            meta: ['reason' => $reason],
            onlyFrom: $from,
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Tracking (poll, webhook) and receipt
    |--------------------------------------------------------------------------
    */

    /**
     * Reads Bolt's ride details and applies them. Respects Bolt's fair-use
     * rule (one details call per ride per 10 s) unless $force.
     */
    public function sync(DeliveryJob $job, bool $force = false): DeliveryJob
    {
        if (! $job->isBolt() || $job->provider_ref === null || ! $job->isRideActive()) {
            return $job;
        }

        $poll = max(1, (int) config('delivery.bolt.poll_seconds', 10));
        if (! $force && $job->last_polled_at !== null && $job->last_polled_at->gt(now()->subSeconds($poll))) {
            return $job;
        }

        try {
            $details = $this->bolt->client()->rideDetails($job->provider_ref);
        } catch (BoltApiException $e) {
            DeliveryJob::query()->whereKey($job->id)->update(['last_polled_at' => now()]);
            if ($e->is('MISSING_ORDER', 'INACTIVE_RIDE_REQUESTED')) {
                Log::error("[bolt] ride {$job->provider_ref} of job #{$job->id} is unknown or inactive at Bolt", $e->context());
            }

            return $job->fresh();
        }

        return $this->apply($job, $details);
    }

    /**
     * Applies a ride state from Bolt (a details poll, or a webhook whose
     * details call failed). Stores what Bolt said, then moves the job.
     */
    public function apply(DeliveryJob $job, BoltRideDetails $details): DeliveryJob
    {
        $job = $job->fresh(['order']);
        if ($job->isTerminal() || $job->provider_ref === null) {
            return $job;
        }

        // A driver who drops the ride sends Bolt back to SEARCHING: forget them.
        $driver = $details->state === 'SEARCHING' ? null : ($details->driver() ?? $job->provider_driver);
        DeliveryJob::query()->whereKey($job->id)->update([
            'provider_state' => $details->state,
            'provider_driver' => $driver === null ? null : json_encode($driver),
            'last_polled_at' => now(),
        ]);
        $job->refresh();

        $target = BoltStates::forDelivery($details->state, parcelInCar: $job->status === 'on_the_way' || $job->picked_up_at !== null);
        if ($target === null) {
            Log::info("[bolt] unknown ride state {$details->state} on job #{$job->id}; stored, no status change");

            return $job;
        }
        if ($target['status'] === $job->status || ! $job->canTransitionTo($target['status'])) {
            return $job;
        }

        $attributes = ['failure_reason' => $target['failure_reason']];
        if (in_array($target['status'], ['on_the_way', 'delivered'], true) && $job->picked_up_at === null) {
            $attributes['picked_up_at'] = now();
        }

        try {
            $job = $this->stateMachine->transition($job, $target['status'], null, 'provider', $attributes,
                meta: array_filter(['bolt_state' => $details->state, 'reason' => $target['failure_reason']]),
                inTransaction: function (DeliveryJob $fresh) use ($target) {
                    if ($target['status'] === 'delivered') {
                        $this->completeOrder($fresh->order);
                    }
                },
            );
        } catch (DeliveryActionException) {
            return $job->fresh(); // changed by someone else in the meantime
        }

        if (in_array($target['failure_reason'], ['provider_cancelled_in_transit', 'client_cancelled_in_transit', 'payment_booking_failed'], true)) {
            Log::critical("[bolt] job #{$job->id} (order {$job->order->code}) failed: {$target['failure_reason']}. Support must act.", [
                'ride_id' => $job->provider_ref, 'bolt_state' => $details->state,
            ]);
        }
        if ($target['status'] === 'delivered') {
            $job = $this->fetchReceipt($job);
        }

        return $job;
    }

    /**
     * What the ride really cost Sokoni, from Bolt's receipt. Retried by the
     * minute cron until it arrives (Bolt: minutes, rarely 24 h+).
     */
    public function fetchReceipt(DeliveryJob $job): DeliveryJob
    {
        if ($job->provider_ref === null || $job->status !== 'delivered' || $job->final_cost !== null) {
            return $job;
        }

        try {
            $receipt = $this->bolt->client()->receipt($job->provider_ref);
        } catch (BoltApiException) {
            DeliveryJob::query()->whereKey($job->id)->update(['receipt_checked_at' => now()]);

            return $job->fresh();
        }

        $sameCurrency = $receipt->currency === null || $receipt->currency === ($job->currency ?? 'TZS');
        $difference = $sameCurrency ? round((float) $job->fee - $receipt->amount, 2) : null;

        DeliveryJob::query()->whereKey($job->id)->update([
            'final_cost' => $receipt->amount,
            'cost_difference' => $difference,
            'receipt' => json_encode($receipt->toArray()),
            'receipt_checked_at' => now(),
        ]);
        $this->stateMachine->record($job, null, 'provider', 'provider_receipt', meta: [
            'amount' => $receipt->amount, 'currency' => $receipt->currency, 'fee_charged' => (int) $job->fee, 'difference' => $difference,
        ]);

        return $job->fresh();
    }

    /** Every booked ride whose details are due (the 10-second tracker). */
    public function syncActive(): int
    {
        $synced = 0;
        $poll = max(1, (int) config('delivery.bolt.poll_seconds', 10));

        DeliveryJob::query()
            ->where('provider', DeliveryJob::PROVIDER_BOLT)
            ->whereIn('status', DeliveryJob::RIDE_ACTIVE_STATUSES)
            ->whereNotNull('provider_ref')
            ->where(fn ($q) => $q->whereNull('last_polled_at')->orWhere('last_polled_at', '<=', now()->subSeconds($poll - 1)))
            ->orderBy('id')
            ->each(function (DeliveryJob $job) use (&$synced) {
                try {
                    $this->sync($job, force: true);
                    $synced++;
                } catch (Throwable $e) {
                    Log::error("[bolt] tracking job #{$job->id} failed: ".$e->getMessage());
                }
            });

        return $synced;
    }

    /**
     * The minute cron: quotes and bookings waiting out a Bolt error, ready
     * orders whose booking never ran, and receipts still to collect.
     *
     * @return array{quoted: int, booked: int, receipts: int}
     */
    public function processDue(): array
    {
        $counts = ['quoted' => 0, 'booked' => 0, 'receipts' => 0];

        DeliveryJob::query()->where('provider', DeliveryJob::PROVIDER_BOLT)->where('status', 'pending_quote')
            ->where(fn ($q) => $q->whereNull('next_booking_at')->orWhere('next_booking_at', '<=', now()))
            ->where('created_at', '<=', now()->subSeconds(30))
            ->orderBy('id')
            ->each(function (DeliveryJob $job) use (&$counts) {
                if ($this->quote($job)->status !== 'pending_quote') {
                    $counts['quoted']++;
                }
            });

        DeliveryJob::query()->where('provider', DeliveryJob::PROVIDER_BOLT)->where('status', 'awaiting_ready')
            ->where('payment_status', 'paid')
            ->whereHas('order', fn ($q) => $q->where('status', 'ready'))
            ->where(fn ($q) => $q->whereNull('next_booking_at')->orWhere('next_booking_at', '<=', now()))
            ->orderBy('id')
            ->each(function (DeliveryJob $job) use (&$counts) {
                if ($this->bookIfReady($job)->status !== 'awaiting_ready') {
                    $counts['booked']++;
                }
            });

        DeliveryJob::query()->where('provider', DeliveryJob::PROVIDER_BOLT)->where('status', 'delivered')
            ->whereNull('final_cost')->whereNotNull('provider_ref')
            ->where('delivered_at', '>=', now()->subDays(3))
            ->where(fn ($q) => $q->whereNull('receipt_checked_at')->orWhere('receipt_checked_at', '<=', now()->subMinutes(5)))
            ->orderBy('id')
            ->each(function (DeliveryJob $job) use (&$counts) {
                if ($this->fetchReceipt($job)->final_cost !== null) {
                    $counts['receipts']++;
                }
            });

        return $counts;
    }

    /*
    |--------------------------------------------------------------------------
    | Buyer, seller and admin actions
    |--------------------------------------------------------------------------
    */

    /**
     * Buyer, seller or admin cancels. The Bolt ride is cancelled first if
     * the parcel hasn't left the shop. Once it has, only an admin may.
     *
     * Money: a full refund of a paid fee, except that a BUYER cancelling
     * after Bolt assigned a driver forfeits the cancellation fee (setting
     * delivery_buyer_cancel_fee), which covers Bolt's own charge.
     *
     * @param  bool  $cancelOrder  false when the order itself is already being cancelled (observer path)
     */
    public function cancel(DeliveryJob $job, ?User $actor, string $role, ?string $reason, bool $cancelOrder = true): DeliveryJob
    {
        $job = $job->fresh(['order']);
        if ($job->isTerminal()) {
            return $job;
        }

        // Never cancel from a 10-second-old status: the parcel may just have left the shop.
        if ($job->isRideActive()) {
            $job = $this->sync($job, force: true);
            if ($job->isTerminal()) {
                return $job;
            }
        }

        if (! $job->parcelAtShop() && $role !== 'admin') {
            throw DeliveryActionException::make('cannot_cancel_in_transit', 409);
        }

        $driverAssigned = in_array($job->status, ['driver_assigned', 'arrived_at_shop'], true);
        $paid = $job->payment_status === 'paid';
        $kept = $driverAssigned && $role === 'buyer' && $paid ? min(DeliverySettings::buyerCancelFee(), (int) $job->fee) : 0;
        $refund = $paid ? (int) $job->fee - $kept : 0;
        $sellerAfterAssign = $driverAssigned && $role === 'seller';

        if ($job->provider_ref !== null && in_array($job->provider_state, BoltStates::BEFORE_PICKUP, true)) {
            $this->cancelRide($job, $job->provider_ref, $actor, $role);
        }

        return $this->stateMachine->transition($job, 'cancelled', $actor, $role,
            [
                'cancelled_by' => $role,
                'cancel_reason' => $reason === null ? null : mb_substr($reason, 0, 255),
                'earned_amount' => 0,
                'refund_amount' => $refund,
                'refund_status' => $refund > 0 ? 'pending' : null,
            ],
            note: $reason,
            meta: array_filter([
                'refund_amount' => $refund,
                'kept_amount' => $kept ?: null,
                'reason' => $sellerAfterAssign ? self::SELLER_CANCELLED_AFTER_ASSIGN : null,
            ], fn ($v) => $v !== null),
            inTransaction: function (DeliveryJob $fresh) use ($cancelOrder, $reason, $sellerAfterAssign, $actor, $refund) {
                if ($sellerAfterAssign) {
                    // Seller cancels after a Bolt driver was on the way: Sokoni absorbs Bolt's charge; kept for audit.
                    $this->stateMachine->record($fresh, $actor, 'seller', self::SELLER_CANCELLED_AFTER_ASSIGN, note: $reason, meta: [
                        'reason' => self::SELLER_CANCELLED_AFTER_ASSIGN,
                        'ride_id' => $fresh->provider_ref,
                        'buyer_refund' => $refund,
                        'bolt_charge_funded_by' => 'platform',
                    ]);
                }
                if ($cancelOrder) {
                    $this->cancelOrder($fresh->order, $reason ?? __('delivery.reasons.delivery_cancelled'));
                }
            },
        );
    }

    /**
     * Cancels every job still waiting for its delivery fee after the
     * configured timeout (default 20 minutes from the price being set),
     * together with its order. The status is re-checked under the row
     * lock, so a payment landing at the same moment wins.
     *
     * @return int how many jobs were cancelled
     */
    public function expireUnpaidJobs(): int
    {
        $minutes = DeliverySettings::unpaidTimeoutMinutes();
        $cutoff = now()->subMinutes($minutes);
        $expired = 0;

        DeliveryJob::query()
            ->where('status', 'pending_payment')
            ->where(fn ($q) => $q->where('pending_payment_at', '<=', $cutoff)
                ->orWhere(fn ($old) => $old->whereNull('pending_payment_at')->where('created_at', '<=', $cutoff)))
            ->orderBy('id')
            ->each(function (DeliveryJob $job) use (&$expired, $minutes) {
                try {
                    $this->stateMachine->transition($job, 'cancelled', null, 'system',
                        [
                            'cancelled_by' => 'system',
                            'cancel_reason' => self::PAYMENT_TIMEOUT,
                            'earned_amount' => 0,
                            'refund_amount' => 0,
                        ],
                        meta: ['reason' => self::PAYMENT_TIMEOUT, 'timeout_minutes' => $minutes],
                        inTransaction: function (DeliveryJob $fresh) {
                            $order = $fresh->order;
                            $this->cancelOrder($order, __('delivery.reasons.payment_timeout', [], $order->seller?->user?->preferredLocale()));
                        },
                        onlyFrom: ['pending_payment'],
                    );
                    $expired++;
                } catch (DeliveryActionException) {
                    // Paid or cancelled between the query and the row lock: leave it alone.
                }
            });

        return $expired;
    }

    /**
     * No Bolt driver, or a ride that failed before pickup: try again. A
     * paid fee carries over (book again once the order is ready); an
     * unpaid one is priced again.
     */
    public function retry(DeliveryJob $job, User $buyer): DeliveryJob
    {
        $job = $job->fresh();
        if (! in_array($job->status, ['no_driver_found', 'failed'], true) || ! BoltStates::retryable($job->failure_reason)) {
            throw DeliveryActionException::make('cannot_retry', 409);
        }

        $to = $job->payment_status === 'paid' ? 'awaiting_ready' : 'pending_quote';
        $job = $this->stateMachine->transition($job, $to, $buyer, 'buyer', [
            'failure_reason' => null,
            'provider_ref' => null,
            'provider_state' => null,
            'provider_driver' => null,
            'booking_attempts' => 0,
            'next_booking_at' => null,
        ], note: 'retry', meta: array_filter(['previous_ride_id' => $job->provider_ref]));

        return $to === 'pending_quote' ? $this->quote($job) : $this->bookIfReady($job);
    }

    /** The buyer collects from the shop instead. Any paid fee is refunded in full. */
    public function switchToPickup(DeliveryJob $job, User $buyer): DeliveryJob
    {
        $job = $job->fresh(['order']);
        if ($job->isRideActive()) {
            $job = $this->sync($job, force: true);
        }
        $allowed = in_array($job->status, ['pending_quote', 'pending_payment', 'awaiting_ready', 'pending_driver', 'no_driver_found'], true)
            || ($job->status === 'failed' && $job->parcelAtShop());
        if (! $allowed) {
            throw DeliveryActionException::make('cannot_switch_to_pickup', 409);
        }

        if ($job->provider_ref !== null && in_array($job->provider_state, BoltStates::BEFORE_PICKUP, true)) {
            $this->cancelRide($job, $job->provider_ref, $buyer, 'buyer');
        }

        $refund = $job->payment_status === 'paid' ? (int) $job->fee : 0;

        return $this->stateMachine->transition($job, 'cancelled', $buyer, 'buyer',
            [
                'cancelled_by' => 'buyer',
                'cancel_reason' => 'switched_to_pickup',
                'earned_amount' => 0,
                'refund_amount' => $refund,
                'refund_status' => $refund > 0 ? 'pending' : null,
            ],
            note: 'switched_to_pickup',
            meta: ['refund_amount' => $refund],
            inTransaction: function (DeliveryJob $fresh) {
                $fresh->order->forceFill(['delivery_method' => 'pickup', 'payment_method' => 'pay_on_pickup'])->saveQuietly();
            },
        );
    }

    /** The order was cancelled through the existing order endpoint (observer path). */
    public function orderWasCancelled(Order $order, ?User $actor): void
    {
        $job = $order->deliveryJob()->first();
        if ($job === null || $job->isTerminal()) {
            return;
        }

        $role = match (true) {
            $actor === null => 'system',
            $actor->id === $order->buyer_id => 'buyer',
            $actor->sellerProfileId() === $order->seller_id => 'seller',
            default => 'admin',
        };

        try {
            $this->cancel($job, $actor, $role, $order->cancelled_reason, cancelOrder: false);
        } catch (Throwable $e) {
            Log::error("[delivery] could not cancel job #{$job->id} after order #{$order->id} was cancelled: ".$e->getMessage());
        }
    }

    /**
     * Cancels the Bolt ride. If Bolt refuses or can't be reached the job is
     * still cancelled locally (the order is off, the shop won't hand over),
     * and the failure goes on the timeline for an admin to check the bill.
     */
    private function cancelRide(DeliveryJob $job, string $rideId, ?User $actor = null, string $role = 'system'): void
    {
        try {
            $this->bolt->client()->cancelRide($rideId);
            $this->stateMachine->record($job, $actor, $role, 'provider_ride_cancelled', meta: ['ride_id' => $rideId]);
        } catch (BoltApiException $e) {
            Log::error("[bolt] could not cancel ride {$rideId} of job #{$job->id}", $e->context());
            $this->stateMachine->record($job, $actor, $role, 'provider_cancel_failed', note: $e->getMessage(), meta: ['ride_id' => $rideId, ...$e->context()]);
        }
    }

    private function completeOrder(Order $order): void
    {
        // Mirrors OrderController::updateStatus's 'completed' branch, which
        // also unlocks the existing order-gated seller review.
        $order->forceFill([
            'status' => 'completed',
            'ready_at' => $order->ready_at ?? now(),
            'completed_at' => now(),
            'payment_status' => 'paid',
        ])->saveQuietly();
    }

    private function cancelOrder(Order $order, string $reason): void
    {
        if (in_array($order->status, ['completed', 'cancelled'], true)) {
            return;
        }
        $order->forceFill([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancelled_reason' => $reason,
        ])->saveQuietly();
    }
}
