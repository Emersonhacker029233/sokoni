<?php

namespace App\Http\Controllers\Api\Delivery;

use App\Http\Controllers\Controller;
use App\Http\Requests\Delivery\DriverJobStatusRequest;
use App\Http\Requests\Delivery\RateDeliveryRequest;
use App\Http\Resources\Delivery\DeliveryJobResource;
use App\Models\DeliveryJob;
use App\Models\DriverProfile;
use App\Services\Delivery\DeliveryJobService;
use App\Services\Delivery\DriverEarnings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DriverJobController extends Controller
{
    public function __construct(private readonly DeliveryJobService $jobs) {}

    public function active(Request $request): JsonResponse
    {
        $job = $this->driver($request)->activeJob();

        return response()->json(['data' => $job ? DeliveryJobResource::for($job, DeliveryJobResource::DRIVER) : null]);
    }

    public function index(Request $request): JsonResponse
    {
        $jobs = $this->driver($request)->jobs()
            ->whereIn('status', ['delivered', 'cancelled', 'failed'])
            ->latest('id')
            ->paginate(20);

        return response()->json([
            'data' => $jobs->getCollection()->map(fn ($j) => DeliveryJobResource::for($j, DeliveryJobResource::DRIVER)->resolve($request)),
            'meta' => ['current_page' => $jobs->currentPage(), 'last_page' => $jobs->lastPage(), 'total' => $jobs->total()],
        ]);
    }

    public function show(Request $request, DeliveryJob $job): DeliveryJobResource
    {
        $this->authorize('actAsDriver', $job);

        return DeliveryJobResource::for($job, DeliveryJobResource::DRIVER);
    }

    /** POST /driver/jobs/{job}/status (multipart when sending a proof photo). */
    public function updateStatus(DriverJobStatusRequest $request, DeliveryJob $job): DeliveryJobResource
    {
        $this->authorize('actAsDriver', $job);
        $driver = $this->driver($request);
        $status = $request->string('status')->toString();

        $job = match ($status) {
            'cancelled' => $this->jobs->driverCancel($job, $driver, $request->input('note')),
            'failed' => $this->jobs->driverFail($job, $driver, $request->string('reason')->toString(), $request->input('note')),
            default => $this->jobs->driverAdvance($job, $driver, $status, $request->only(['pickup_code', 'pin']), $request->file('proof_photo')),
        };

        // After a driver cancel the job belongs to nobody yet; return the driver's last view of it.
        return DeliveryJobResource::for($job->fresh(), DeliveryJobResource::DRIVER);
    }

    public function callAttempt(Request $request, DeliveryJob $job): JsonResponse
    {
        $this->authorize('actAsDriver', $job);
        $job = $this->jobs->recordCallAttempt($job, $this->driver($request));

        return response()->json(['data' => ['call_attempts' => $job->call_attempts]]);
    }

    public function rate(RateDeliveryRequest $request, DeliveryJob $job): JsonResponse
    {
        $this->authorize('actAsDriver', $job);
        $this->jobs->rate($job, $request->user(), 'driver_to_buyer', $request->integer('rating'), $request->input('comment'));

        return response()->json(['message' => 'Thanks for rating.'], 201);
    }

    /** GET /driver/earnings?period=day|week&date=YYYY-MM-DD */
    public function earnings(Request $request, DriverEarnings $earnings): JsonResponse
    {
        $data = $request->validate([
            'period' => ['nullable', 'in:day,week'],
            'date' => ['nullable', 'date'],
        ]);
        $driver = $this->driver($request);
        [$from, $to] = DriverEarnings::period($data['period'] ?? 'day', $data['date'] ?? null);

        return response()->json(['data' => [
            'summary' => $earnings->summary($driver, $from, $to),
            'balance' => $earnings->balance($driver),
            'payouts' => $driver->payouts()->latest('id')->limit(20)->get(['id', 'amount', 'status', 'method', 'reference', 'period_start', 'period_end', 'paid_at']),
            'commission_pct' => \App\Support\DeliverySettings::commissionPct(),
        ]]);
    }

    private function driver(Request $request): DriverProfile
    {
        return $request->attributes->get('driver');
    }
}
