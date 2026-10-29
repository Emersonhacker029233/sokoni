<?php

namespace App\Observers;

use App\Models\Order;
use App\Services\Delivery\DeliveryJobService;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Bridges the EXISTING order endpoints (unchanged) to the delivery system.
 * Only `fulfilment = 'dispatch'` orders are affected; for every legacy
 * order (all of Sokoni 1.0's) both branches return immediately.
 *
 * - seller accepts  -> a delivery job is created and priced
 * - order cancelled -> its open delivery job is cancelled with the agreed money rules
 *
 * The delivery side writes orders with saveQuietly(), so it never loops back here.
 */
class OrderDeliveryObserver
{
    public function __construct(private readonly DeliveryJobService $jobs) {}

    public function updated(Order $order): void
    {
        if (! DeliveryJobService::enabled() || ! $order->isDispatch() || ! $order->wasChanged('status')) {
            return;
        }

        try {
            match ($order->status) {
                'accepted' => $this->jobs->createForAcceptedOrder($order),
                'cancelled' => $this->jobs->orderWasCancelled($order, auth()->user()),
                default => null,
            };
        } catch (Throwable $e) {
            // Never 500 the seller's accept tap because of the delivery side.
            Log::error("[delivery] observer failed for order #{$order->id}: ".$e->getMessage());
            report($e);
        }
    }
}
