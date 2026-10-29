<?php

namespace App\Http\Controllers\Api\Delivery;

use App\Http\Controllers\Controller;
use App\Http\Resources\Delivery\DeliveryJobResource;
use App\Http\Resources\Delivery\DeliveryOfferResource;
use App\Models\DeliveryJob;
use App\Models\DriverProfile;
use App\Services\Delivery\DispatchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class DriverOfferController extends Controller
{
    public function __construct(private readonly DispatchService $dispatch) {}

    /** GET /driver/offers. Polled by the app while online (FCM is the fast path). */
    public function index(Request $request): AnonymousResourceCollection
    {
        // No queue worker on shared hosting: polling doubles as a wave clock.
        $this->dispatch->advanceAllDueThrottled();

        /** @var DriverProfile $driver */
        $driver = $request->attributes->get('driver');

        return DeliveryOfferResource::collection($this->dispatch->openOffersFor($driver));
    }

    /** POST /driver/offers/{job}/accept. First driver wins; everyone else gets 409 already_taken. */
    public function accept(Request $request, DeliveryJob $job): DeliveryJobResource
    {
        $job = $this->dispatch->accept($job, $request->attributes->get('driver'));

        return DeliveryJobResource::for($job, DeliveryJobResource::DRIVER);
    }

    public function decline(Request $request, DeliveryJob $job): JsonResponse
    {
        $this->dispatch->decline($job, $request->attributes->get('driver'));

        return response()->json(['message' => 'Declined.']);
    }
}
