<?php

namespace App\Http\Controllers\Api\Delivery;

use App\Http\Controllers\Controller;
use App\Http\Requests\Delivery\DeliveryQuoteRequest;
use App\Http\Requests\Delivery\RateDeliveryRequest;
use App\Http\Resources\Delivery\DeliveryJobResource;
use App\Models\DeliveryJob;
use App\Models\Order;
use App\Models\SellerProfile;
use App\Services\Delivery\DeliveryActionException;
use App\Services\Delivery\DeliveryJobService;
use App\Services\Delivery\DeliveryUnavailableException;
use App\Services\Delivery\DispatchService;
use App\Services\Delivery\FeeCalculator;
use App\Services\Delivery\LiveTracking;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** The Sokoni customer app's side of a dispatch order (buyer acts, seller reads). */
class CustomerDeliveryController extends Controller
{
    public function __construct(
        private readonly DeliveryJobService $jobs,
        private readonly DispatchService $dispatch,
    ) {}

    /** GET /delivery/quote?seller_id&lat&lng. Estimate shown at checkout; the binding quote is taken when the seller accepts. */
    public function quote(DeliveryQuoteRequest $request, FeeCalculator $fees): JsonResponse
    {
        $seller = SellerProfile::query()->findOrFail($request->integer('seller_id'));
        if ($seller->lat === null || $seller->lng === null) {
            throw DeliveryActionException::make('shop_has_no_location', 422);
        }

        try {
            $quote = $fees->quote((float) $seller->lat, (float) $seller->lng, $request->float('lat'), $request->float('lng'));
        } catch (DeliveryUnavailableException $e) {
            return response()->json(['code' => $e->reason, 'message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => [...$quote->toArray(), 'estimate' => true]]);
    }

    /** GET /delivery/orders/{order}. Polled every few seconds while the tracking screen is open. */
    public function show(Request $request, Order $order): JsonResponse
    {
        $job = $this->jobFor($order);
        $this->authorize('view', $job);

        if ($job->status === 'pending_driver') {
            $this->dispatch->advanceIfDue($job);
            $job->refresh();
        }

        $viewer = $request->user()->can('isBuyer', $job) ? DeliveryJobResource::BUYER : DeliveryJobResource::SELLER;

        return response()->json(['data' => DeliveryJobResource::for($job, $viewer)->resolve($request)]);
    }

    public function cancel(Request $request, Order $order): JsonResponse
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:255']]);
        $job = $this->buyersJob($request, $order);
        $job = $this->jobs->cancel($job, $request->user(), 'buyer', $data['reason'] ?? null);

        return $this->buyerResponse($request, $job);
    }

    public function retry(Request $request, Order $order): JsonResponse
    {
        $job = $this->jobs->retry($this->buyersJob($request, $order), $request->user());

        return $this->buyerResponse($request, $job);
    }

    public function switchToPickup(Request $request, Order $order): JsonResponse
    {
        $job = $this->jobs->switchToPickup($this->buyersJob($request, $order), $request->user());

        return $this->buyerResponse($request, $job);
    }

    public function rate(RateDeliveryRequest $request, Order $order): JsonResponse
    {
        $job = $this->buyersJob($request, $order);
        $this->jobs->rate($job, $request->user(), 'buyer_to_driver', $request->integer('rating'), $request->input('comment'));

        return response()->json(['message' => 'Thanks for rating.'], 201);
    }

    /**
     * POST /delivery/realtime-token. Firebase credentials for the live map,
     * only for someone who is the buyer or driver of a job active right now.
     */
    public function realtimeToken(Request $request, LiveTracking $tracking): JsonResponse
    {
        $user = $request->user();
        $hasActive = DeliveryJob::query()
            ->whereIn('status', DeliveryJob::DRIVER_ACTIVE_STATUSES)
            ->where(fn ($q) => $q
                ->whereHas('order', fn ($o) => $o->where('buyer_id', $user->id))
                ->orWhere('driver_profile_id', $user->driverProfile?->id ?? 0))
            ->exists();

        if (! $hasActive) {
            throw DeliveryActionException::make('no_active_job', 403);
        }

        $credentials = $tracking->isEnabled() ? $tracking->credentialsFor($user) : null;
        if ($credentials === null) {
            return response()->json(['code' => 'tracking_unavailable', 'message' => __('delivery.errors.tracking_unavailable'), 'fallback' => 'poll'], 503);
        }

        return response()->json(['data' => $credentials]);
    }

    private function jobFor(Order $order): DeliveryJob
    {
        return $order->deliveryJob ?? throw DeliveryActionException::make('no_delivery_job', 404);
    }

    private function buyersJob(Request $request, Order $order): DeliveryJob
    {
        $job = $this->jobFor($order);
        $this->authorize('actAsBuyer', $job);

        return $job;
    }

    private function buyerResponse(Request $request, DeliveryJob $job): JsonResponse
    {
        return response()->json(['data' => DeliveryJobResource::for($job->fresh(), DeliveryJobResource::BUYER)->resolve($request)]);
    }
}
