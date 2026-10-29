<?php

namespace App\Services\Delivery;

use App\Models\DeliveryJob;
use App\Models\DeliveryOffer;
use App\Models\DriverProfile;
use App\Support\DeliverySettings;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Finding a driver for a `pending_driver` job.
 *
 * Waves: radius 1 (3 km) is offered immediately. If nobody accepts within
 * `delivery_dispatch_wave_seconds` (60), the next radius (5 km, then 8 km)
 * is offered to drivers not yet asked. After the last wave times out the
 * job becomes `no_driver_found` and the buyer chooses what to do.
 *
 * There is no queue worker on shared hosting, so waves advance from two
 * places: the every-minute cron (`delivery:dispatch-tick`) and lazily on
 * reads (driver offers list, buyer job polling). Both are safe to run
 * concurrently: every advance re-checks the job under a row lock.
 */
class DispatchService
{
    public function __construct(
        private readonly DeliveryStateMachine $stateMachine,
        private readonly DeliveryMessages $messages,
    ) {}

    /** (Re)start dispatch from wave 1. The job must already be `pending_driver`. */
    public function start(DeliveryJob $job): void
    {
        $job = DB::transaction(function () use ($job) {
            $fresh = DeliveryJob::query()->lockForUpdate()->findOrFail($job->id);
            if ($fresh->status !== 'pending_driver') {
                return null;
            }
            $fresh->forceFill(['dispatch_wave' => 1, 'wave_started_at' => now()])->save();
            $this->stateMachine->record($fresh, null, 'system', 'offer_wave', meta: [
                'wave' => 1, 'radius_km' => DeliverySettings::dispatchRadiiKm()[0],
            ]);

            return $fresh;
        });

        if ($job !== null) {
            $this->offerWithinRadius($job, DeliverySettings::dispatchRadiiKm()[0], 1);
        }
    }

    /**
     * Advance this job's wave if the current one has timed out. Returns the
     * job's status afterwards.
     */
    public function advanceIfDue(DeliveryJob $job): string
    {
        $radii = DeliverySettings::dispatchRadiiKm();
        $cutoff = now()->subSeconds(DeliverySettings::dispatchWaveSeconds());

        $result = DB::transaction(function () use ($job, $radii, $cutoff) {
            $fresh = DeliveryJob::query()->lockForUpdate()->findOrFail($job->id);
            if ($fresh->status !== 'pending_driver' || $fresh->wave_started_at === null || $fresh->wave_started_at->gt($cutoff)) {
                return ['status' => $fresh->status, 'job' => $fresh, 'action' => null];
            }

            if ($fresh->dispatch_wave < count($radii)) {
                $wave = $fresh->dispatch_wave + 1;
                $fresh->forceFill(['dispatch_wave' => $wave, 'wave_started_at' => now()])->save();
                $this->stateMachine->record($fresh, null, 'system', 'offer_wave', meta: [
                    'wave' => $wave, 'radius_km' => $radii[$wave - 1],
                ]);

                return ['status' => 'pending_driver', 'job' => $fresh, 'action' => 'wave', 'wave' => $wave];
            }

            return ['status' => 'pending_driver', 'job' => $fresh, 'action' => 'exhausted'];
        });

        if ($result['action'] === 'wave') {
            $this->offerWithinRadius($result['job'], $radii[$result['wave'] - 1], $result['wave']);
        }

        if ($result['action'] === 'exhausted') {
            try {
                $this->stateMachine->transition($result['job'], 'no_driver_found', null, 'system');
            } catch (DeliveryActionException) {
                // Someone accepted in the instant between the two transactions. Fine.
                return $result['job']->fresh()->status;
            }
            $this->withdrawOpenOffers($result['job'], 'expired');

            return 'no_driver_found';
        }

        return $result['status'];
    }

    /** Advance every overdue job. Called by the cron tick. */
    public function advanceAllDue(): int
    {
        $cutoff = now()->subSeconds(DeliverySettings::dispatchWaveSeconds());

        $due = DeliveryJob::query()
            ->where('status', 'pending_driver')
            ->where('wave_started_at', '<=', $cutoff)
            ->orderBy('wave_started_at')
            ->limit(50)
            ->get();

        foreach ($due as $job) {
            $this->advanceIfDue($job);
        }

        return $due->count();
    }

    /**
     * Cheap lazy variant for request paths: at most one caller every few
     * seconds actually does the work, everybody else returns at once.
     */
    public function advanceAllDueThrottled(): void
    {
        Cache::lock('delivery:advance-due', 5)->get(fn () => $this->advanceAllDue());
    }

    /** Offer the current wave's radius to drivers who came online since it opened. Called by the cron tick. */
    public function topUpOpenWaves(): void
    {
        $radii = DeliverySettings::dispatchRadiiKm();
        DeliveryJob::query()->where('status', 'pending_driver')->where('dispatch_wave', '>', 0)->limit(50)->get()
            ->each(fn (DeliveryJob $job) => $this->offerWithinRadius($job, $radii[min($job->dispatch_wave, count($radii)) - 1], $job->dispatch_wave));
    }

    /**
     * Race-safe claim. A single conditional UPDATE decides the winner: it
     * only matches while the job is still `pending_driver` with no driver,
     * so exactly one concurrent caller sees one affected row. The driver's
     * own row is locked first so one driver can never win two jobs at once.
     *
     * @throws DeliveryActionException not_offered (403) | driver_busy (409) | already_taken (409) | vehicle_unverified (403)
     */
    public function accept(DeliveryJob $job, DriverProfile $driver): DeliveryJob
    {
        $losers = DB::transaction(function () use ($job, $driver) {
            $lockedDriver = DriverProfile::query()->lockForUpdate()->findOrFail($driver->id);

            $offer = DeliveryOffer::query()
                ->where('delivery_job_id', $job->id)
                ->where('driver_profile_id', $lockedDriver->id)
                ->lockForUpdate()
                ->first();
            if ($offer === null || $offer->status !== 'offered') {
                throw DeliveryActionException::make($offer?->status === 'lost' ? 'already_taken' : 'not_offered', $offer?->status === 'lost' ? 409 : 403);
            }
            if (! $lockedDriver->isApproved()) {
                throw DeliveryActionException::make('driver_not_approved', 403);
            }
            if ($lockedDriver->hasActiveJob()) {
                throw DeliveryActionException::make('driver_busy', 409);
            }
            $vehicle = $lockedDriver->activeVehicle()->whereNotNull('verified_at')->first();
            if ($vehicle === null) {
                throw DeliveryActionException::make('vehicle_unverified', 403);
            }

            $now = now();
            $claimed = DeliveryJob::query()
                ->whereKey($job->id)
                ->where('status', 'pending_driver')
                ->whereNull('driver_profile_id')
                ->update([
                    'driver_profile_id' => $lockedDriver->id,
                    'vehicle_id' => $vehicle->id,
                    'status' => 'driver_assigned',
                    'driver_assigned_at' => $now,
                    'updated_at' => $now,
                ]);

            if ($claimed !== 1) {
                // Lost the race. The winner's transaction marks this offer `lost`.
                throw DeliveryActionException::make('already_taken', 409);
            }

            $offer->forceFill(['status' => 'won', 'responded_at' => $now])->save();

            $losers = DeliveryOffer::query()
                ->where('delivery_job_id', $job->id)
                ->where('status', 'offered')
                ->with('driver.user')
                ->get();
            DeliveryOffer::query()->whereIn('id', $losers->pluck('id'))->update(['status' => 'lost', 'responded_at' => $now]);

            $this->stateMachine->record(
                DeliveryJob::query()->findOrFail($job->id),
                $lockedDriver->user,
                'driver',
                'status',
                'pending_driver',
                'driver_assigned',
                meta: ['distance_km' => $offer->distance_km],
            );

            return $losers;
        });

        $assigned = DeliveryJob::query()->findOrFail($job->id);
        $this->stateMachine->announce($assigned, 'pending_driver', 'driver_assigned');
        foreach ($losers as $offer) {
            $this->messages->offerWithdrawn($offer->driver->user, $assigned);
        }

        return $assigned;
    }

    public function decline(DeliveryJob $job, DriverProfile $driver): void
    {
        DeliveryOffer::query()
            ->where('delivery_job_id', $job->id)
            ->where('driver_profile_id', $driver->id)
            ->where('status', 'offered')
            ->update(['status' => 'declined', 'responded_at' => now()]);
    }

    /** Open offers this driver can still accept, newest first. */
    public function openOffersFor(DriverProfile $driver): Collection
    {
        return DeliveryOffer::query()
            ->where('driver_profile_id', $driver->id)
            ->where('status', 'offered')
            ->whereHas('job', fn ($q) => $q->where('status', 'pending_driver'))
            ->with(['job.order.seller', 'job.order.items'])
            ->latest('offered_at')
            ->get();
    }

    /** Mark every still-open offer for this job (e.g. on cancel / no driver) and ping those drivers silently. */
    public function withdrawOpenOffers(DeliveryJob $job, string $status = 'withdrawn'): void
    {
        $open = DeliveryOffer::query()->where('delivery_job_id', $job->id)->where('status', 'offered')->with('driver.user')->get();
        if ($open->isEmpty()) {
            return;
        }
        DeliveryOffer::query()->whereIn('id', $open->pluck('id'))->update(['status' => $status, 'responded_at' => now()]);
        foreach ($open as $offer) {
            $this->messages->offerWithdrawn($offer->driver->user, $job);
        }
    }

    /**
     * Approved, online, recently located, idle drivers with a verified
     * vehicle within $radiusKm of the shop, nearest first. Never the
     * order's own buyer or seller, and never someone who already has an
     * open offer for, declined, or cancelled this job.
     *
     * @return Collection<int, array{driver: DriverProfile, distance_km: float}>
     */
    public function candidates(DeliveryJob $job, float $radiusKm): Collection
    {
        $job->loadMissing('order.seller');
        [$minLat, $maxLat, $minLng, $maxLng] = Haversine::boundingBox($job->pickup_lat, $job->pickup_lng, $radiusKm);

        $excludedDriverIds = DeliveryOffer::query()
            ->where('delivery_job_id', $job->id)
            ->whereIn('status', ['offered', 'declined', 'cancelled'])
            ->pluck('driver_profile_id');

        $excludedUserIds = array_filter([$job->order->buyer_id, $job->order->seller?->user_id]);

        return DriverProfile::query()
            ->with('user')
            ->where('status', 'approved')
            ->where('is_online', true)
            ->where('last_located_at', '>=', now()->subSeconds((int) config('delivery.driver_online_ttl_seconds')))
            ->whereBetween('last_lat', [$minLat, $maxLat])
            ->whereBetween('last_lng', [$minLng, $maxLng])
            ->whereNotIn('user_id', $excludedUserIds)
            ->whereNotIn('id', $excludedDriverIds)
            ->whereHas('vehicles', fn ($q) => $q->where('is_active', true)->whereNotNull('verified_at'))
            ->whereDoesntHave('jobs', fn ($q) => $q->whereIn('status', DeliveryJob::DRIVER_ACTIVE_STATUSES))
            ->get()
            ->map(fn (DriverProfile $d) => [
                'driver' => $d,
                'distance_km' => Haversine::km($job->pickup_lat, $job->pickup_lng, $d->last_lat, $d->last_lng),
            ])
            ->filter(fn (array $c) => $c['distance_km'] <= $radiusKm)
            ->sortBy('distance_km')
            ->take((int) config('delivery.max_offers_per_wave'))
            ->values();
    }

    private function offerWithinRadius(DeliveryJob $job, float $radiusKm, int $wave): int
    {
        $candidates = $this->candidates($job, $radiusKm);

        foreach ($candidates as $candidate) {
            DeliveryOffer::query()->updateOrCreate(
                ['delivery_job_id' => $job->id, 'driver_profile_id' => $candidate['driver']->id],
                [
                    'wave' => $wave,
                    'distance_km' => round($candidate['distance_km'], 2),
                    'status' => 'offered',
                    'offered_at' => now(),
                    'responded_at' => null,
                ],
            );
            $this->messages->offer($candidate['driver']->user, $job, $candidate['distance_km']);
        }

        return $candidates->count();
    }

    /** For DeliveryJobService: a driver who accepted and then cancelled is never re-offered this job. */
    public function markDriverCancelled(DeliveryJob $job, DriverProfile $driver): void
    {
        DeliveryOffer::query()
            ->where('delivery_job_id', $job->id)
            ->where('driver_profile_id', $driver->id)
            ->update(['status' => 'cancelled', 'responded_at' => now()]);
    }
}
