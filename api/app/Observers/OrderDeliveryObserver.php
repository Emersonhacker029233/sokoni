<?php

namespace App\Observers;

use App\Models\Order;
use App\Services\Delivery\DeliveryJobService;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Bridges the EXISTING order endpoints (unchanged) to Bolt delivery.
 * Only `fulfilment = 'dispatch'` orders are affected; for every other
 * order (all of Sokoni 1.0's) each branch returns immediately.
 *
 * - shop accepts       -> a delivery job is created and priced from Bolt
 * - shop marks ready   -> the Bolt ride is booked, if the fee is paid
 * - order cancelled    -> the open delivery (and its Bolt ride) is cancelled
 *
 * The delivery side writes orders with saveQuietly(), so it never loops back here.
 */
class OrderDeliveryObserver
{
    public function __construct(private readonly DeliveryJobService $jobs) {}

    public function updated(Order $order): void
    {
        if (! $order->isDispatch() || ! $order->wasChanged('status')) {
            return;
        }

        try {
            match ($order->status) {
                'accepted' => $this->jobs->createForAcceptedOrder($order),
                'ready' => $this->jobs->orderBecameReady($order),
                'cancelled' => $this->jobs->orderWasCancelled($order, auth()->user()),
                default => null,
            };
        } catch (Throwable $e) {
            // Never 500 the seller's tap because of the delivery side; the minute cron retries.
            Log::error("[delivery] observer failed for order #{$order->id}: ".$e->getMessage());
            report($e);
        }
    }
}
