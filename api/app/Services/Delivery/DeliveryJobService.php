<?php

namespace App\Services\Delivery;

use App\Models\DeliveryJob;
use App\Models\DeliveryJobEvent;
use App\Models\DeliveryRating;
use App\Models\DriverProfile;
use App\Models\Order;
use App\Models\User;
use App\Support\DeliverySettings;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Everything that happens to a delivery job apart from dispatch itself.
 * Money outcomes follow docs/delivery/PLAN.md section 6 (client-approved
 * 2026-10-29). Order rows are written with saveQuietly() so the delivery
 * order observer never re-enters while the delivery side is the one
 * changing the order.
 */
class DeliveryJobService
{
    /** Driver-reported failure reasons the app may send. */
    public const FAILURE_REASONS = ['customer_unreachable', 'vehicle_breakdown', 'accident', 'item_damaged', 'wrong_address', 'other'];

    public function __construct(
        private readonly FeeCalculator $fees,
        private readonly DeliveryStateMachine $stateMachine,
        private readonly DispatchService $dispatch,
        private readonly DeliveryMessages $messages,
    ) {}

    public static function enabled(): bool
    {
        return (bool) config('delivery.enabled');
    }

    /**
     * Called when the seller accepts a dispatch order. Freezes the quote
     * and the pickup/drop-off snapshot, then waits for payment (or, with
     * cash fees enabled, starts dispatch straight away).
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

        // Max distance was already enforced at checkout. The shop could have
        // moved since, but the seller has accepted, so price it anyway.
        $quote = $this->fees->quote((float) $seller->lat, (float) $seller->lng, (float) $order->delivery_lat, (float) $order->delivery_lng, enforceMaxDistance: false);
        $cash = DeliverySettings::feeCashEnabled();

        $job = DB::transaction(function () use ($order, $seller, $quote, $cash) {
            $job = new DeliveryJob;
            $job->forceFill([
                'order_id' => $order->id,
                'status' => $cash ? 'pending_driver' : 'pending_payment',
                'pickup_lat' => $seller->lat,
                'pickup_lng' => $seller->lng,
                'pickup_address' => $seller->address,
                'dropoff_lat' => $order->delivery_lat,
                'dropoff_lng' => $order->delivery_lng,
                'dropoff_address' => $order->address,
                'distance_km' => $quote->distanceKm,
                'distance_source' => $quote->distanceSource,
                'base_fee' => $quote->baseFee,
                'per_km_fee' => $quote->perKmFee,
                'fee' => $quote->fee,
                'commission_pct' => $quote->commissionPct,
                'commission' => $quote->commission,
                'driver_earnings' => $quote->driverEarnings,
                'payment_status' => $cash ? 'cash' : 'unpaid',
                'delivery_pin' => str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT),
                'pending_driver_at' => $cash ? now() : null,
            ])->save();

            $this->stateMachine->record($job, null, 'system', 'status', null, $job->status, meta: $quote->toArray());

            return $job;
        });

        $this->messages->jobCreated($job);
        if ($job->status === 'pending_driver') {
            $this->dispatch->start($job);
        }

        return $job;
    }

    /**
     * The delivery fee has been confirmed paid by a VERIFIED server-side
     * source (payment webhook / status query, phase 5). Idempotent: a
     * repeat confirmation for an already-paid job is a no-op.
     */
    public function markFeePaid(DeliveryJob $job, ?string $reference = null): DeliveryJob
    {
        if ($job->fresh()->payment_status === 'paid') {
            return $job->fresh();
        }

        try {
            $job = $this->stateMachine->transition($job, 'pending_driver', null, 'system',
                ['payment_status' => 'paid', 'paid_at' => now()],
                meta: array_filter(['reference' => $reference]),
            );
        } catch (DeliveryActionException $e) {
            // A concurrent confirmation won the row lock first: already paid, nothing to do.
            if ($job->fresh()->payment_status === 'paid') {
                return $job->fresh();
            }
            // Paid after the job was cancelled: keep the money traceable for a refund.
            if ($job->fresh()->status === 'cancelled') {
                DeliveryJob::query()->whereKey($job->id)->update([
                    'payment_status' => 'paid', 'paid_at' => now(),
                    'refund_amount' => $job->fee, 'refund_status' => 'pending',
                ]);

                return $job->fresh();
            }
            throw $e;
        }
        $this->dispatch->start($job);

        return $job->fresh();
    }

    /**
     * Buyer, seller or admin cancels. Not allowed once the goods have left
     * the shop (PLAN.md section 6: contact support instead).
     *
     * @param  bool  $cancelOrder  false when the order itself is already being cancelled (observer path)
     */
    public function cancel(DeliveryJob $job, ?User $actor, string $role, ?string $reason, bool $cancelOrder = true): DeliveryJob
    {
        $job = $job->fresh();
        if ($job->isTerminal()) {
            return $job;
        }
        if ($job->isInTransit() && $role !== 'admin') {
            throw DeliveryActionException::make('cannot_cancel_in_transit', 409);
        }
        if ($job->status === 'failed' && $job->failure_reason === 'customer_unreachable') {
            throw DeliveryActionException::make('invalid_transition', 409, ['from' => $job->status, 'to' => 'cancelled']);
        }

        $driverAssigned = in_array($job->status, ['driver_assigned', 'arrived_at_shop'], true);
        $paid = $job->payment_status === 'paid';
        $cancelFee = DeliverySettings::buyerCancelFee();

        [$earned, $refund] = match (true) {
            $driverAssigned && $role === 'buyer' => [$paid ? min($cancelFee, $job->fee) : 0, $paid ? $job->fee - min($cancelFee, $job->fee) : 0],
            // Seller (or admin) cancelled: the buyer did nothing wrong.
            $driverAssigned => [DeliverySettings::sellerCancelCompensatesDriver() ? $cancelFee : 0, $paid ? $job->fee : 0],
            default => [0, $paid ? $job->fee : 0],
        };

        $job = $this->stateMachine->transition($job, 'cancelled', $actor, $role,
            [
                'cancelled_by' => $role,
                'cancel_reason' => $reason,
                'earned_amount' => $earned,
                'refund_amount' => $refund,
                'refund_status' => $refund > 0 ? 'pending' : null,
            ],
            note: $reason,
            meta: ['earned_amount' => $earned, 'refund_amount' => $refund],
            inTransaction: function (DeliveryJob $fresh) use ($cancelOrder, $reason) {
                if ($cancelOrder) {
                    $this->cancelOrder($fresh->order, $reason ?? __('delivery.reasons.delivery_cancelled'));
                }
            },
        );
        $this->dispatch->withdrawOpenOffers($job);

        return $job;
    }

    /** No driver found (or a driver-side failure): try again with the fee already paid. */
    public function retry(DeliveryJob $job, User $buyer): DeliveryJob
    {
        $job = $job->fresh();
        if (! in_array($job->status, ['no_driver_found', 'failed'], true) || $job->failure_reason === 'customer_unreachable') {
            throw DeliveryActionException::make('cannot_retry', 409);
        }

        $job = $this->stateMachine->transition($job, 'pending_driver', $buyer, 'buyer', [
            'driver_profile_id' => null,
            'vehicle_id' => null,
            'dispatch_wave' => 0,
            'wave_started_at' => null,
            'pin_attempts' => 0,
            'call_attempts' => 0,
        ], note: 'retry');
        $this->dispatch->start($job);

        return $job->fresh();
    }

    /** The buyer gives up on delivery and collects from the shop instead. Full fee refund. */
    public function switchToPickup(DeliveryJob $job, User $buyer): DeliveryJob
    {
        $job = $job->fresh();
        $allowed = in_array($job->status, ['pending_payment', 'pending_driver', 'no_driver_found'], true)
            || ($job->status === 'failed' && $job->failure_reason !== 'customer_unreachable');
        if (! $allowed) {
            throw DeliveryActionException::make('cannot_switch_to_pickup', 409);
        }

        $refund = $job->payment_status === 'paid' ? $job->fee : 0;
        $job = $this->stateMachine->transition($job, 'cancelled', $buyer, 'buyer',
            [
                'cancelled_by' => 'buyer',
                'cancel_reason' => 'switched_to_pickup',
                'earned_amount' => 0,
                'refund_amount' => $refund,
                'refund_status' => $refund > 0 ? 'pending' : null,
            ],
            note: 'switched_to_pickup',
            inTransaction: function (DeliveryJob $fresh) {
                $order = $fresh->order;
                $order->forceFill(['delivery_method' => 'pickup', 'payment_method' => 'pay_on_pickup'])->saveQuietly();
            },
        );
        $this->dispatch->withdrawOpenOffers($job);

        return $job;
    }

    /**
     * Driver backs out before pickup: the job goes back into dispatch from
     * wave 1, never to this driver again, and the cancellation is counted.
     */
    public function driverCancel(DeliveryJob $job, DriverProfile $driver, ?string $reason): DeliveryJob
    {
        $job = $this->assertDriversJob($job, $driver);
        if (! in_array($job->status, ['driver_assigned', 'arrived_at_shop'], true)) {
            throw DeliveryActionException::make('cannot_cancel_in_transit', 409);
        }

        $job = $this->stateMachine->transition($job, 'pending_driver', $driver->user, 'driver',
            ['driver_profile_id' => null, 'vehicle_id' => null, 'dispatch_wave' => 0, 'wave_started_at' => null],
            note: $reason,
            meta: ['driver_profile_id' => $driver->id, 'driver_cancelled' => true],
            inTransaction: function (DeliveryJob $fresh) use ($driver, $reason) {
                $this->dispatch->markDriverCancelled($fresh, $driver);
                $this->stateMachine->record($fresh, $driver->user, 'driver', 'driver_cancelled', note: $reason);
                $driver->increment('cancellations_count');

                $recent = DeliveryJobEvent::query()
                    ->where('actor_user_id', $driver->user_id)
                    ->where('type', 'driver_cancelled')
                    ->where('created_at', '>=', now()->subDays(7))
                    ->count();
                if ($recent >= (int) config('delivery.driver_cancel_flag_threshold') && $driver->flagged_at === null) {
                    $driver->forceFill(['flagged_at' => now(), 'flag_reason' => "{$recent} cancellations in 7 days"])->save();
                }
            },
        );
        $this->dispatch->start($job);

        return $job->fresh();
    }

    /**
     * Driver moves the job forward: arrived_at_shop, picked_up (needs the
     * pickup code), on_the_way, arrived, delivered (needs the PIN, and a
     * photo if required).
     *
     * @param  array{pickup_code?: string|null, pin?: string|null}  $input
     */
    public function driverAdvance(DeliveryJob $job, DriverProfile $driver, string $to, array $input = [], ?UploadedFile $proofPhoto = null): DeliveryJob
    {
        $job = $this->assertDriversJob($job, $driver);
        if (! in_array($to, ['arrived_at_shop', 'picked_up', 'on_the_way', 'arrived', 'delivered'], true) || ! $job->canTransitionTo($to)) {
            throw DeliveryActionException::make('invalid_transition', 409, ['from' => $job->status, 'to' => $to]);
        }

        $attributes = [];
        $meta = [];

        if ($to === 'arrived_at_shop' && $driver->last_lat !== null) {
            $meta['distance_from_shop_m'] = (int) round(Haversine::km($driver->last_lat, $driver->last_lng, $job->pickup_lat, $job->pickup_lng) * 1000);
        }

        if ($to === 'picked_up') {
            $code = strtoupper(trim((string) ($input['pickup_code'] ?? '')));
            if ($code === '' || ! hash_equals($job->pickupCode(), $code)) {
                $this->stateMachine->record($job, $driver->user, 'driver', 'pickup_code_failed');
                throw DeliveryActionException::make('wrong_pickup_code');
            }
            if ($job->order->status === 'cancelled') {
                throw DeliveryActionException::make('order_cancelled', 409);
            }
        }

        if ($to === 'delivered') {
            $this->checkPin($job, $driver, (string) ($input['pin'] ?? ''));

            if (DeliverySettings::proofPhotoRequired() && $proofPhoto === null) {
                throw DeliveryActionException::make('proof_photo_required');
            }
            if ($proofPhoto !== null) {
                $attributes['proof_photo'] = $proofPhoto->store("delivery/proof/{$job->id}", 'local');
            }
            $attributes['earned_amount'] = $job->driver_earnings;
        }

        return $this->stateMachine->transition($job, $to, $driver->user, 'driver', $attributes, meta: $meta,
            inTransaction: function (DeliveryJob $fresh) use ($to, $driver) {
                if ($to === 'delivered') {
                    $this->completeOrder($fresh->order);
                    $driver->increment('jobs_completed');
                }
            },
        );
    }

    /** Driver can't finish a job that has left the shop. */
    public function driverFail(DeliveryJob $job, DriverProfile $driver, string $reason, ?string $note): DeliveryJob
    {
        $job = $this->assertDriversJob($job, $driver);
        if (! in_array($reason, self::FAILURE_REASONS, true)) {
            throw DeliveryActionException::make('invalid_reason');
        }
        if (! $job->canTransitionTo('failed')) {
            throw DeliveryActionException::make('invalid_transition', 409, ['from' => $job->status, 'to' => 'failed']);
        }

        $unreachable = $reason === 'customer_unreachable';
        if ($unreachable) {
            $waitMinutes = (int) config('delivery.unreachable_wait_minutes');
            $minCalls = (int) config('delivery.unreachable_min_call_attempts');
            if ($job->status !== 'arrived' || $job->arrived_at === null || $job->arrived_at->gt(now()->subMinutes($waitMinutes))) {
                throw DeliveryActionException::make('unreachable_too_early', 409, ['minutes' => $waitMinutes]);
            }
            if ($job->call_attempts < $minCalls) {
                throw DeliveryActionException::make('unreachable_needs_calls', 409, ['calls' => $minCalls]);
            }
        }

        return $this->stateMachine->transition($job, 'failed', $driver->user, 'driver',
            [
                'failure_reason' => $reason,
                'failure_note' => $note,
                // Unreachable buyer: the driver did the work and keeps the
                // earnings, the fee is not refunded. A driver-side failure
                // earns nothing; an admin can adjust it.
                'earned_amount' => $unreachable ? $job->driver_earnings : 0,
            ],
            note: $note,
            meta: ['reason' => $reason],
            inTransaction: function (DeliveryJob $fresh) use ($unreachable) {
                if ($unreachable) {
                    $this->cancelOrder($fresh->order, __('delivery.reasons.customer_unreachable'));
                }
            },
        );
    }

    /** Logged each time the driver taps "Call customer"; the unreachable rule counts these. */
    public function recordCallAttempt(DeliveryJob $job, DriverProfile $driver): DeliveryJob
    {
        $job = $this->assertDriversJob($job, $driver);
        if (! $job->isDriverActive()) {
            throw DeliveryActionException::make('job_not_active', 409);
        }
        $job->increment('call_attempts');
        $this->stateMachine->record($job, $driver->user, 'driver', 'call_attempt');

        return $job->fresh();
    }

    /**
     * Two-way rating after delivery. One per direction per job.
     *
     * @param  'buyer_to_driver'|'driver_to_buyer'  $direction
     */
    public function rate(DeliveryJob $job, User $rater, string $direction, int $rating, ?string $comment): DeliveryRating
    {
        $job->loadMissing(['order', 'driver']);
        if ($job->status !== 'delivered' || $job->driver === null) {
            throw DeliveryActionException::make('cannot_rate_yet', 409);
        }
        if ($job->ratings()->where('direction', $direction)->exists()) {
            throw DeliveryActionException::make('already_rated', 409);
        }

        $ratee = $direction === 'buyer_to_driver' ? $job->driver->user_id : $job->order->buyer_id;

        return DB::transaction(function () use ($job, $rater, $direction, $rating, $comment, $ratee) {
            $row = DeliveryRating::query()->create([
                'delivery_job_id' => $job->id,
                'rater_user_id' => $rater->id,
                'ratee_user_id' => $ratee,
                'direction' => $direction,
                'rating' => $rating,
                'comment' => $comment,
            ]);

            if ($direction === 'buyer_to_driver') {
                $stats = DeliveryRating::query()->where('ratee_user_id', $ratee)->where('direction', 'buyer_to_driver')
                    ->selectRaw('AVG(rating) as avg, COUNT(*) as cnt')->first();
                DriverProfile::query()->whereKey($job->driver_profile_id)->update([
                    'rating_avg' => round((float) $stats->avg, 2),
                    'rating_count' => (int) $stats->cnt,
                ]);
            }

            return $row;
        });
    }

    /** Called by the order observer when the order is cancelled through the existing order endpoint. */
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

    private function checkPin(DeliveryJob $job, DriverProfile $driver, string $pin): void
    {
        if ($job->pin_locked_at !== null) {
            throw DeliveryActionException::make('pin_locked', 423);
        }
        if ($pin !== '' && hash_equals((string) $job->delivery_pin, trim($pin))) {
            return;
        }

        $max = (int) config('delivery.pin_max_attempts');
        $attempts = $job->pin_attempts + 1;
        $locked = $attempts >= $max;

        DeliveryJob::query()->whereKey($job->id)->update([
            'pin_attempts' => $attempts,
            'pin_locked_at' => $locked ? now() : null,
        ]);
        $this->stateMachine->record($job, $driver->user, 'driver', $locked ? 'pin_locked' : 'pin_failed', meta: ['attempts' => $attempts]);

        if ($locked) {
            Log::warning("[delivery] PIN locked on job #{$job->id} after {$attempts} attempts");
            $this->messages->pinLocked($job);
            throw DeliveryActionException::make('pin_locked', 423);
        }

        throw DeliveryActionException::make('wrong_pin', 422, [], ['attempts_left' => $max - $attempts]);
    }

    private function assertDriversJob(DeliveryJob $job, DriverProfile $driver): DeliveryJob
    {
        $job = $job->fresh(['order']);
        if ($job->driver_profile_id !== $driver->id) {
            throw DeliveryActionException::make('not_your_job', 403);
        }

        return $job;
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
