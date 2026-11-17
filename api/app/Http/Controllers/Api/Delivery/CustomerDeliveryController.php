<?php

namespace App\Http\Controllers\Api\Delivery;

use App\Http\Controllers\Controller;
use App\Http\Requests\Delivery\DeliveryQuoteRequest;
use App\Http\Requests\Delivery\PayDeliveryRequest;
use App\Http\Resources\Delivery\DeliveryJobResource;
use App\Models\DeliveryJob;
use App\Models\Order;
use App\Models\SellerProfile;
use App\Services\Delivery\Bolt\BoltApiException;
use App\Services\Delivery\DeliveryActionException;
use App\Services\Delivery\DeliveryJobService;
use App\Services\Delivery\DeliveryPaymentService;
use App\Services\Delivery\DeliveryQuoter;
use App\Services\Delivery\DeliveryUnavailableException;
use App\Support\DeliveryAccess;
use App\Support\DeliverySettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * The Sokoni app's side of a Bolt delivery: the buyer acts, the seller
 * reads. Every route here is behind `delivery.enabled`, `auth:sanctum` and
 * `delivery.access` (routes/api_delivery.php), so it 404s for anyone the
 * dark launch doesn't include.
 */
class CustomerDeliveryController extends Controller
{
    public function __construct(private readonly DeliveryJobService $jobs) {}

    /** GET /delivery/config. How the delivery fee is paid and how often to poll. */
    public function config(Request $request, DeliveryPaymentService $payments): JsonResponse
    {
        $capabilities = $payments->capabilities();

        return response()->json(['data' => [
            'enabled' => true,
            'provider' => 'bolt',
            'min_app_version' => '1.1.0',
            'currency' => 'TZS',
            'max_distance_km' => DeliverySettings::maxDistanceKm(),
            'unpaid_timeout_minutes' => DeliverySettings::unpaidTimeoutMinutes(),
            'payment' => [
                'mode' => 'online',
                'online_available' => $capabilities->collections,
                'sandbox' => $capabilities->sandbox,
                'channels' => $capabilities->channels,
                'covers' => ['delivery_fee'],
                'goods_paid_to' => 'shop',
            ],
            'tracking' => ['poll_seconds' => max(5, (int) config('delivery.bolt.poll_seconds', 10))],
            'returns_enabled' => DeliveryAccess::returnsEnabledFor($request->user()),
        ]]);
    }

    /**
     * GET /delivery/quote?seller_id&lat&lng. Bolt's estimate for checkout:
     * the range, and the fee the buyer will pay (its upper bound). The
     * binding fee is set when the shop accepts.
     */
    public function quote(DeliveryQuoteRequest $request, DeliveryQuoter $quoter): JsonResponse
    {
        $shop = SellerProfile::query()->with('user')->findOrFail($request->integer('seller_id'));
        if (! DeliveryAccess::orderEligible($request->user(), $shop)) {
            throw DeliveryActionException::make('not_available', 422);
        }

        try {
            ['estimate' => $estimate, 'distance_km' => $km] = $quoter->quote($shop, $request->float('lat'), $request->float('lng'));
        } catch (DeliveryUnavailableException $e) {
            return response()->json(['code' => $e->reason, 'message' => $e->getMessage()], 422);
        } catch (BoltApiException) {
            throw DeliveryActionException::make('quote_unavailable', 503);
        }

        return response()->json(['data' => [
            'provider' => 'bolt',
            'distance_km' => $km,
            'currency' => $estimate->currency ?? 'TZS',
            'min' => $estimate->minAmount,
            'max' => $estimate->maxAmount,
            'fee' => $estimate->customerFee(),
            'category' => $estimate->category,
            'eta_seconds' => $estimate->etaSeconds,
            'estimate' => true,
        ]]);
    }

    /** GET /delivery/orders/{order}. Polled every few seconds while the order screen is open. */
    public function show(Request $request, Order $order, DeliveryPaymentService $payments): JsonResponse
    {
        $job = $this->jobFor($order);
        $this->authorize('viewInCustomerApp', $job);

        if ($job->status === 'pending_payment') {
            // The buyer is waiting on their phone's prompt: ask the gateway (throttled).
            $payments->refreshForJob($job);
            $job->refresh();
        }
        if ($job->isRideActive() && Cache::add("delivery:lazy-sync:{$job->id}", true, max(1, (int) config('delivery.bolt.poll_seconds', 10)))) {
            // Fresher than the 10-second cron when someone is looking, within Bolt's fair-use rule.
            $job = $this->jobs->sync($job);
        }

        $viewer = $request->user()->can('isBuyer', $job) ? DeliveryJobResource::BUYER : DeliveryJobResource::SELLER;

        return response()->json(['data' => DeliveryJobResource::for($job->fresh(), $viewer)->resolve($request)]);
    }

    /**
     * POST /delivery/orders/{order}/pay. Sends the payment prompt to the
     * buyer's phone and answers 202 with the attempt pending. The job is
     * marked paid later, only when the gateway confirms it to the server.
     */
    public function pay(PayDeliveryRequest $request, Order $order, DeliveryPaymentService $payments): JsonResponse
    {
        $job = $this->buyersJob($order);
        $payments->start($job, $request->user(), $request->string('channel'), $request->string('msisdn'), $request->string('idempotency_key'));

        return $this->buyerResponse($request, $job)->setStatusCode(202);
    }

    public function cancel(Request $request, Order $order): JsonResponse
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:255']]);
        $job = $this->jobs->cancel($this->buyersJob($order), $request->user(), 'buyer', $data['reason'] ?? null);

        return $this->buyerResponse($request, $job);
    }

    public function retry(Request $request, Order $order): JsonResponse
    {
        $job = $this->jobs->retry($this->buyersJob($order), $request->user());

        return $this->buyerResponse($request, $job);
    }

    public function switchToPickup(Request $request, Order $order): JsonResponse
    {
        $job = $this->jobs->switchToPickup($this->buyersJob($order), $request->user());

        return $this->buyerResponse($request, $job);
    }

    private function jobFor(Order $order): DeliveryJob
    {
        return $order->deliveryJob ?? throw DeliveryActionException::make('no_delivery_job', 404);
    }

    private function buyersJob(Order $order): DeliveryJob
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
