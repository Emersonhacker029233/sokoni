<?php

namespace App\Services\Returns;

use App\Models\DeliveryJob;
use App\Models\Order;
use App\Models\ReturnRequest;
use App\Models\ReturnRequestPhoto;
use App\Models\User;
use App\Services\Delivery\Bolt\BoltApiException;
use App\Services\Delivery\Bolt\BoltRideBooker;
use App\Services\Delivery\Bolt\BoltRideDetails;
use App\Services\Delivery\Bolt\BoltStates;
use App\Services\Delivery\DeliveryUnavailableException;
use App\Services\Moderation\BlockList;
use App\Support\DeliveryAccess;
use App\Support\ReturnPolicy;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Returns (docs/delivery/RETURNS.md), the only writer of return_requests.
 *
 *   buyer requests (reason, 1-3 photos, comment) within the shop's window
 *   -> shop accepts or rejects (with a reason) within 48 h, else Sokoni
 *      support decides; the buyer may dispute a rejection
 *   -> accepted: the Bolt ride back is priced (customer -> shop). The
 *      buyer pays it for "changed my mind"; otherwise it is recorded as
 *      owed by the seller and the buyer books straight away
 *   -> Bolt brings the item back -> the shop confirms "received, refund
 *      done" -> refunded
 */
class ReturnService
{
    public function __construct(
        private readonly BoltRideBooker $bolt,
        private readonly ReturnMessages $messages,
        private readonly BlockList $blocks,
    ) {}

    /*
    |--------------------------------------------------------------------------
    | Eligibility and the request
    |--------------------------------------------------------------------------
    */

    /** The delivered Bolt job, or null when this order can't have a return at all. */
    public function deliveredJob(Order $order): ?DeliveryJob
    {
        $job = $order->isDispatch() ? $order->deliveryJob : null;

        return $job?->status === 'delivered' ? $job : null;
    }

    /**
     * Why the buyer can't request a return of this order right now, or
     * null when they can.
     */
    public function refusal(Order $order, User $buyer): ?string
    {
        if ($order->buyer_id !== $buyer->id) {
            return 'not_your_return';
        }
        $job = $this->deliveredJob($order);
        if ($job === null) {
            return 'return_not_available';
        }
        if (! ReturnPolicy::accepts($order->return_policy)) {
            return 'no_return_policy';
        }
        if (now()->gt(ReturnPolicy::deadline($order->return_policy, $job->delivered_at))) {
            return 'return_window_closed';
        }
        if (ReturnRequest::query()->where('order_id', $order->id)->exists()) {
            return 'return_exists';
        }

        return null;
    }

    /**
     * @param  list<UploadedFile>  $photos  1-3 images (validated by the request)
     */
    public function create(Order $order, User $buyer, string $reason, ?string $comment, array $photos): ReturnRequest
    {
        $order->loadMissing(['seller.user', 'deliveryJob']);
        if (($refusal = $this->refusal($order, $buyer)) !== null) {
            throw ReturnActionException::make($refusal, $refusal === 'not_your_return' ? 403 : 409);
        }

        $shopOwner = $order->seller?->user;
        // The buyer blocked the shop: contact is off until they unblock (same rule as orders and chat).
        if ($shopOwner !== null && $this->blocks->hasBlocked($buyer, $shopOwner->id)) {
            throw new ReturnActionException('blocked', __('returns.errors.handled_by_support'), 403);
        }
        // The shop blocked the buyer: a block must not be a way out of a return. Sokoni support decides instead.
        $sellerBlockedBuyer = $shopOwner !== null && $this->blocks->hasBlocked($shopOwner, $buyer->id);

        $stored = [];
        try {
            $return = DB::transaction(function () use ($order, $buyer, $reason, $comment, $photos, $sellerBlockedBuyer, &$stored) {
                $return = new ReturnRequest;
                $return->forceFill([
                    'order_id' => $order->id,
                    'buyer_id' => $buyer->id,
                    'shop_id' => $order->seller_id,
                    'reason' => $reason,
                    'comment' => $comment !== null && trim($comment) !== '' ? trim($comment) : null,
                    'status' => 'requested',
                    'payer' => ReturnRequest::payerFor($reason),
                    'escalated_at' => $sellerBlockedBuyer ? now() : null,
                    'escalation_reason' => $sellerBlockedBuyer ? 'seller_blocked_buyer' : null,
                ])->save();

                foreach (array_values($photos) as $i => $photo) {
                    $path = $photo->store("returns/{$return->id}", 'local');
                    $stored[] = $path;
                    (new ReturnRequestPhoto)->forceFill(['return_request_id' => $return->id, 'path' => $path, 'sort' => $i])->save();
                }

                return $return;
            });
        } catch (Throwable $e) {
            Storage::disk('local')->delete($stored);
            throw $e;
        }

        $this->messages->toBuyer($return, 'requested');
        $this->messages->toSeller($return, 'requested');

        return $return;
    }

    /*
    |--------------------------------------------------------------------------
    | Decisions
    |--------------------------------------------------------------------------
    */

    /** The shop (or Sokoni support) accepts: the ride back is priced and the buyer told what to do next. */
    public function accept(ReturnRequest $return, User $actor, string $role, ?string $note = null): ReturnRequest
    {
        $return = $this->transition($return, 'accepted', function (ReturnRequest $locked) use ($actor, $role, $note) {
            if ($role === 'seller' && $locked->status !== 'requested') {
                throw ReturnActionException::make('invalid_transition', 409, ['from' => $locked->status, 'to' => 'accepted']);
            }
            if ($role === 'seller' && $locked->escalated_at !== null && $locked->escalation_reason === 'seller_blocked_buyer') {
                throw ReturnActionException::make('handled_by_support', 409);
            }

            return [
                'decided_by' => $actor->id,
                'decided_role' => $role,
                'decided_at' => now(),
                'decision_note' => $role === 'seller' ? $note : $locked->decision_note,
                'resolution_note' => $role === 'admin' ? $note : $locked->resolution_note,
                'fee_status' => $locked->payer === 'seller' ? 'owed_by_seller' : 'unpaid',
            ];
        });

        $return = $this->quoteRide($return);

        if ($role === 'admin') {
            $this->messages->toBuyer($return, 'admin_accepted');
            $this->messages->toSeller($return, 'admin_accepted');
        } else {
            $this->messages->toBuyer($return, match (true) {
                $return->ride_status === 'unavailable' => 'accepted_no_ride',
                $return->payer === 'buyer' => 'accepted_buyer_pays',
                default => 'accepted_seller_pays',
            });
        }

        return $return;
    }

    /** The shop rejects, with a reason the buyer sees. */
    public function reject(ReturnRequest $return, User $seller, string $reason): ReturnRequest
    {
        $return = $this->transition($return, 'rejected', function (ReturnRequest $locked) use ($seller, $reason) {
            if ($locked->status !== 'requested') {
                throw ReturnActionException::make('invalid_transition', 409, ['from' => $locked->status, 'to' => 'rejected']);
            }
            if ($locked->escalation_reason === 'seller_blocked_buyer') {
                throw ReturnActionException::make('handled_by_support', 409);
            }

            return ['decided_by' => $seller->id, 'decided_role' => 'seller', 'decided_at' => now(), 'decision_note' => $reason];
        });

        $this->messages->toBuyer($return, 'rejected', ['note' => $reason]);

        return $return;
    }

    /** After a rejection the buyer can ask Sokoni support to decide. */
    public function dispute(ReturnRequest $return, User $buyer, string $comment): ReturnRequest
    {
        $return = $this->transition($return, 'disputed', fn () => ['disputed_at' => now(), 'dispute_comment' => $comment]);

        Log::notice("[returns] return #{$return->id} (order {$return->order->code}) disputed; needs Sokoni support");
        $this->messages->toBuyer($return, 'disputed');
        $this->messages->toSeller($return, 'disputed');

        return $return;
    }

    /** The buyer changes their mind about returning, before the item is on its way. */
    public function withdraw(ReturnRequest $return, User $buyer): ReturnRequest
    {
        if (! in_array($return->status, ['requested', 'rejected', 'disputed', 'accepted'], true) || $return->isRideActive()) {
            throw ReturnActionException::make('cannot_withdraw', 409);
        }

        $return = $this->transition($return, 'closed', fn () => ['closed_at' => now(), 'closed_reason' => 'withdrawn']);
        $this->messages->toSeller($return, 'withdrawn');

        return $return;
    }

    /** Sokoni support closes a return (upholding a rejection, or ending it any other way). */
    public function close(ReturnRequest $return, User $admin, string $note): ReturnRequest
    {
        $return = $return->fresh();
        if ($return->isRideActive() && in_array($return->provider_state, BoltStates::BEFORE_PICKUP, true)) {
            $this->cancelRide($return);
        }

        $return = $this->transition($return, 'closed', fn () => [
            'closed_at' => now(), 'closed_reason' => 'admin', 'resolution_note' => $note,
            'decided_by' => $admin->id, 'decided_role' => 'admin',
        ]);
        $this->messages->toBuyer($return, 'closed', ['note' => $note]);
        $this->messages->toSeller($return, 'closed', ['note' => $note]);

        return $return;
    }

    /** Sokoni support changes who pays the ride back, before anything was paid or booked. */
    public function setPayer(ReturnRequest $return, User $admin, string $payer): ReturnRequest
    {
        abort_unless(in_array($payer, ['buyer', 'seller'], true), 422);

        return DB::transaction(function () use ($return, $payer, $admin) {
            $locked = ReturnRequest::query()->lockForUpdate()->findOrFail($return->id);
            if ($locked->fee_status === 'paid' || $locked->provider_ref !== null) {
                throw ReturnActionException::make('invalid_transition', 409, ['from' => $locked->status, 'to' => "payer:{$payer}"]);
            }
            $locked->forceFill([
                'payer' => $payer,
                'fee_status' => $locked->fee_status === null ? null : ($payer === 'seller' ? 'owed_by_seller' : 'unpaid'),
                'resolution_note' => trim(($locked->resolution_note ?? '')."\nPayer set to {$payer} by admin #{$admin->id}"),
            ])->save();

            return $locked;
        });
    }

    /**
     * Shops have 48 hours to answer. Then Sokoni support decides (run by
     * the minute cron).
     *
     * @return int how many were escalated
     */
    public function escalateOverdue(): int
    {
        $count = 0;
        ReturnRequest::query()
            ->where('status', 'requested')
            ->whereNull('escalated_at')
            ->where('created_at', '<=', now()->subHours(ReturnRequest::SELLER_RESPONSE_HOURS))
            ->orderBy('id')
            ->each(function (ReturnRequest $return) use (&$count) {
                $updated = ReturnRequest::query()->whereKey($return->id)->where('status', 'requested')->whereNull('escalated_at')
                    ->update(['escalated_at' => now(), 'escalation_reason' => 'seller_no_response', 'updated_at' => now()]);
                if ($updated === 1) {
                    $count++;
                    $fresh = $return->fresh();
                    Log::notice("[returns] return #{$return->id} escalated: no answer from the shop in 48 h");
                    $this->messages->toBuyer($fresh, 'escalated');
                    $this->messages->toSeller($fresh, 'escalated');
                }
            });

        return $count;
    }

    /*
    |--------------------------------------------------------------------------
    | The ride back
    |--------------------------------------------------------------------------
    */

    /** Bolt's price for customer -> shop; the payer owes its upper bound. */
    public function quoteRide(ReturnRequest $return): ReturnRequest
    {
        $return = $return->fresh(['order', 'shop']);
        if ($return->order->delivery_lat === null || $return->shop->lat === null) {
            return $this->updateRide($return, ['ride_status' => 'unavailable', 'ride_failure_reason' => 'no_location']);
        }

        try {
            $estimate = $this->bolt->quote($return->pickupPoint(), $return->dropoffPoint(), fresh: true);
        } catch (DeliveryUnavailableException $e) {
            return $this->updateRide($return, ['ride_status' => $e->reason === 'area_not_serviced' ? 'unavailable' : 'pending_quote', 'ride_failure_reason' => $e->reason]);
        } catch (BoltApiException $e) {
            Log::warning("[returns] could not price the ride back for return #{$return->id}", $e->context());

            return $this->updateRide($return, ['ride_status' => 'pending_quote', 'ride_failure_reason' => 'quote_failed']);
        }

        return $this->updateRide($return, [
            'ride_status' => 'quoted',
            'ride_failure_reason' => null,
            'fee' => $estimate->customerFee(),
            'estimate_min' => $estimate->minAmount,
            'estimate_max' => $estimate->maxAmount,
            'currency' => $estimate->currency ?? 'TZS',
            'provider_category' => $estimate->category,
        ]);
    }

    /** Verified payment of a buyer-paid ride (DeliveryPaymentService). Idempotent. */
    public function markFeePaid(ReturnRequest $return, ?string $reference = null): ReturnRequest
    {
        $updated = ReturnRequest::query()->whereKey($return->id)->where('fee_status', 'unpaid')
            ->update(['fee_status' => 'paid', 'paid_at' => now(), 'updated_at' => now()]);
        $return = $return->fresh();
        if ($updated === 1) {
            Log::info("[returns] return #{$return->id} ride fee paid".($reference ? " ({$reference})" : ''));
            $this->messages->toBuyer($return, 'fee_paid');
        }

        return $return;
    }

    /**
     * The buyer is ready: book Bolt from them to the shop, with a fresh
     * fare_id. The buyer is Bolt's rider (the driver collects from them).
     */
    public function bookRide(ReturnRequest $return, User $buyer): ReturnRequest
    {
        $return = $return->fresh(['order.buyer', 'shop.user']);
        if ($return->status !== 'accepted' || ! $return->feeSettled() || $return->isRideActive()) {
            throw ReturnActionException::make('ride_not_bookable', 409);
        }
        if ($return->ride_status === 'pending_quote' || $return->fee === null) {
            $return = $this->quoteRide($return);
        }
        if ($return->ride_status === 'unavailable') {
            throw ReturnActionException::make('ride_unavailable', 409);
        }
        if ($return->fee === null) {
            throw ReturnActionException::make('ride_booking_failed', 503);
        }

        $phone = $return->order->delivery_phone ?: $buyer->phone;
        try {
            $booking = $this->bolt->book(
                $return->pickupPoint(),
                $return->dropoffPoint(),
                ['name' => mb_substr((string) ($buyer->name ?: 'Sokoni customer'), 0, 60), 'phone' => (string) $phone],
                mb_substr(sprintf(
                    'Sokoni RETURN of order %s. Collect parcel from customer, deliver to shop: %s %s. Rudisha mzigo dukani.',
                    $return->order->code,
                    $return->shop->shop_name,
                    $return->shop->user?->phone ?? $return->shop->whatsapp ?? '',
                ), 0, 250),
            );
        } catch (DeliveryUnavailableException $e) {
            $this->updateRide($return, ['ride_status' => $e->reason === 'area_not_serviced' ? 'unavailable' : 'no_driver_found', 'ride_failure_reason' => $e->reason]);
            throw ReturnActionException::make($e->reason === 'area_not_serviced' ? 'ride_unavailable' : 'ride_booking_failed', $e->reason === 'area_not_serviced' ? 409 : 503);
        } catch (BoltApiException $e) {
            Log::warning("[returns] booking the ride back for return #{$return->id} failed", $e->context());
            throw ReturnActionException::make('ride_booking_failed', 503);
        }

        $return = $this->transition($return, 'return_in_transit', fn () => [
            'provider' => 'bolt',
            'provider_ref' => $booking['ride_id'],
            'provider_state' => 'SEARCHING',
            'provider_driver' => null,
            'provider_category' => $booking['estimate']->category,
            'ride_status' => 'pending_driver',
            'ride_failure_reason' => null,
            'booked_at' => now(),
            'picked_up_at' => null,
            'last_polled_at' => null,
        ]);

        $this->messages->toBuyer($return, 'ride_booked');
        $this->messages->toSeller($return, 'ride_booked');

        return $return;
    }

    public function syncRide(ReturnRequest $return, bool $force = false): ReturnRequest
    {
        if (! $return->isRideActive()) {
            return $return;
        }
        $poll = max(1, (int) config('delivery.bolt.poll_seconds', 10));
        if (! $force && $return->last_polled_at !== null && $return->last_polled_at->gt(now()->subSeconds($poll))) {
            return $return;
        }

        try {
            $details = $this->bolt->client()->rideDetails($return->provider_ref);
        } catch (BoltApiException) {
            ReturnRequest::query()->whereKey($return->id)->update(['last_polled_at' => now()]);

            return $return->fresh();
        }

        return $this->applyRide($return, $details);
    }

    /** A Bolt ride state for the ride back (poll or webhook). */
    public function applyRide(ReturnRequest $return, BoltRideDetails $details): ReturnRequest
    {
        $return = $return->fresh();
        if ($return->provider_ref === null || ! $return->isRideActive()) {
            return $return;
        }

        $rideStatus = BoltStates::forReturnRide($details->state) ?? $return->ride_status;
        $driver = $details->state === 'SEARCHING' ? null : ($details->driver() ?? $return->provider_driver);
        $pickedUp = $return->picked_up_at ?? (in_array($rideStatus, ['on_the_way', 'delivered'], true) ? now() : null);
        $previous = $return->ride_status;

        ReturnRequest::query()->whereKey($return->id)->update([
            'provider_state' => $details->state,
            'provider_driver' => $driver === null ? null : json_encode($driver),
            'ride_status' => $rideStatus,
            'ride_failure_reason' => in_array($rideStatus, ['failed', 'no_driver_found'], true) ? strtolower($details->state) : null,
            'picked_up_at' => $pickedUp,
            'last_polled_at' => now(),
            'updated_at' => now(),
        ]);
        $return = $return->fresh();

        if ($rideStatus === 'driver_assigned' && $previous !== 'driver_assigned') {
            $this->messages->toBuyer($return, 'ride_driver');
        }

        if ($rideStatus === 'delivered' && $return->status === 'return_in_transit') {
            $return = $this->transition($return, 'received', fn () => ['received_at' => now()]);
            $this->fetchReceipt($return);
            $this->messages->toSeller($return, 'received');
            $this->messages->toBuyer($return, 'received');
        } elseif (in_array($rideStatus, ['failed', 'no_driver_found'], true)) {
            if ($return->picked_up_at === null) {
                // Still with the buyer: back to accepted so they can book again.
                $return = $this->transition($return, 'accepted', fn () => []);
                $this->messages->toBuyer($return, 'ride_failed');
            } else {
                Log::critical("[returns] Bolt ride {$return->provider_ref} of return #{$return->id} ended ({$details->state}) with the item in the car. Support must act.");
            }
        }

        return $return->fresh();
    }

    public function fetchReceipt(ReturnRequest $return): ReturnRequest
    {
        if ($return->provider_ref === null || $return->ride_status !== 'delivered' || $return->final_cost !== null) {
            return $return;
        }
        try {
            $receipt = $this->bolt->client()->receipt($return->provider_ref);
        } catch (BoltApiException) {
            ReturnRequest::query()->whereKey($return->id)->update(['receipt_checked_at' => now()]);

            return $return->fresh();
        }
        ReturnRequest::query()->whereKey($return->id)->update([
            'final_cost' => $receipt->amount,
            'cost_difference' => $receipt->currency === null || $receipt->currency === ($return->currency ?? 'TZS') ? round((float) $return->fee - $receipt->amount, 2) : null,
            'receipt' => json_encode($receipt->toArray()),
            'receipt_checked_at' => now(),
        ]);

        return $return->fresh();
    }

    /** "Item received, refund done": the shop confirms, and the order shows Returned / Refunded. */
    public function confirmRefunded(ReturnRequest $return, User $seller, ?string $note = null): ReturnRequest
    {
        if ($return->escalation_reason === 'seller_blocked_buyer' && $return->needsAdmin()) {
            throw ReturnActionException::make('handled_by_support', 409);
        }
        if ($return->isRideActive() && in_array($return->provider_state, BoltStates::BEFORE_PICKUP, true)) {
            // The item came back another way while a ride was still searching: don't send a driver for nothing.
            $this->cancelRide($return);
        }

        $return = $this->transition($return, 'refunded', fn (ReturnRequest $locked) => [
            'received_at' => $locked->received_at ?? now(),
            'refunded_at' => now(),
            'resolution_note' => $note !== null && trim($note) !== '' ? trim($note) : $locked->resolution_note,
        ]);
        $this->messages->toBuyer($return, 'refunded');

        return $return;
    }

    /** The 10-second tracker and the minute cron. */
    public function syncActiveRides(): int
    {
        $count = 0;
        $poll = max(1, (int) config('delivery.bolt.poll_seconds', 10));
        ReturnRequest::query()->whereIn('ride_status', ReturnRequest::RIDE_ACTIVE)->whereNotNull('provider_ref')
            ->where(fn ($q) => $q->whereNull('last_polled_at')->orWhere('last_polled_at', '<=', now()->subSeconds($poll - 1)))
            ->orderBy('id')
            ->each(function (ReturnRequest $return) use (&$count) {
                try {
                    $this->syncRide($return, force: true);
                    $count++;
                } catch (Throwable $e) {
                    Log::error("[returns] tracking return #{$return->id} failed: ".$e->getMessage());
                }
            });

        return $count;
    }

    /** @return array{escalated: int, return_quotes: int, return_receipts: int} */
    public function processDue(): array
    {
        $counts = ['escalated' => $this->escalateOverdue(), 'return_quotes' => 0, 'return_receipts' => 0];

        ReturnRequest::query()->where('status', 'accepted')->where('ride_status', 'pending_quote')
            ->where('updated_at', '<=', now()->subMinute())
            ->orderBy('id')
            ->each(function (ReturnRequest $return) use (&$counts) {
                if ($this->quoteRide($return)->ride_status === 'quoted') {
                    $counts['return_quotes']++;
                }
            });

        ReturnRequest::query()->where('ride_status', 'delivered')->whereNull('final_cost')->whereNotNull('provider_ref')
            ->where(fn ($q) => $q->whereNull('receipt_checked_at')->orWhere('receipt_checked_at', '<=', now()->subMinutes(5)))
            ->where('updated_at', '>=', now()->subDays(3))
            ->orderBy('id')
            ->each(function (ReturnRequest $return) use (&$counts) {
                if ($this->fetchReceipt($return)->final_cost !== null) {
                    $counts['return_receipts']++;
                }
            });

        return $counts;
    }

    /*
    |--------------------------------------------------------------------------
    | Who may see what
    |--------------------------------------------------------------------------
    */

    public function isBuyer(?User $user, ReturnRequest $return): bool
    {
        return $user !== null && $user->id === $return->buyer_id;
    }

    public function isSeller(?User $user, ReturnRequest $return): bool
    {
        return $user !== null && $user->sellerProfileId() !== null && $user->sellerProfileId() === $return->shop_id;
    }

    /** The shop blocked this buyer: the shop sees no content from them and support decides. */
    public function hiddenFromSeller(ReturnRequest $return, User $seller): bool
    {
        return $this->blocks->hasBlocked($seller, $return->buyer_id);
    }

    /** Both sides of a return are inside the launch, or the return can't be seen at all. */
    public function visibleTo(?User $user, ReturnRequest $return): bool
    {
        return ($this->isBuyer($user, $return) || $this->isSeller($user, $return)) && DeliveryAccess::returnsEnabledFor($user);
    }

    /*
    |--------------------------------------------------------------------------
    | Internals
    |--------------------------------------------------------------------------
    */

    /**
     * Moves a return to $to under a row lock.
     *
     * @param  callable(ReturnRequest): array<string, mixed>  $attributes  extra columns, computed from the locked row
     */
    private function transition(ReturnRequest $return, string $to, callable $attributes): ReturnRequest
    {
        return DB::transaction(function () use ($return, $to, $attributes) {
            /** @var ReturnRequest $locked */
            $locked = ReturnRequest::query()->lockForUpdate()->findOrFail($return->id);
            if (! $locked->canTransitionTo($to)) {
                throw ReturnActionException::make('invalid_transition', 409, ['from' => $locked->status, 'to' => $to]);
            }
            $locked->forceFill(['status' => $to, ...$attributes($locked)])->save();

            return $locked->fresh(['order', 'shop', 'buyer']);
        });
    }

    private function updateRide(ReturnRequest $return, array $attributes): ReturnRequest
    {
        $return->forceFill($attributes)->save();

        return $return->fresh(['order', 'shop', 'buyer']);
    }

    private function cancelRide(ReturnRequest $return): void
    {
        try {
            $this->bolt->client()->cancelRide((string) $return->provider_ref);
            ReturnRequest::query()->whereKey($return->id)->update(['ride_status' => 'cancelled']);
        } catch (BoltApiException $e) {
            Log::error("[returns] could not cancel ride {$return->provider_ref} of return #{$return->id}", $e->context());
        }
    }
}
