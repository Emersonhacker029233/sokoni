<?php

namespace App\Console\Commands;

use App\Models\DeliveryJob;
use App\Services\Delivery\Bolt\BoltApiException;
use App\Services\Delivery\Bolt\BoltClient;
use App\Services\Delivery\DeliveryJobService;
use Illuminate\Console\Command;

/**
 * Drives the Bolt Ride Booker SANDBOX by hand (docs/delivery/BOLT.md,
 * "Sandbox end to end"). Refuses to run with BOLT_ENV=live.
 *
 *   php artisan bolt:sandbox create                  new sandbox; put the uuid in BOLT_SANDBOX_UUID
 *   php artisan bolt:sandbox state 1001 DRIVER_ON_ROUTE_TO_CLIENT
 *   php artisan bolt:sandbox state SK7KX2M9QD COMPLETED   (an order code works too)
 *   php artisan bolt:sandbox destroy                 deletes the BOLT_SANDBOX_UUID sandbox
 *
 * `state` moves the ride as a real driver would and then syncs the Sokoni
 * delivery straight away, so the apps show it on their next poll.
 */
class BoltSandbox extends Command
{
    protected $signature = 'bolt:sandbox {action : create | state | destroy} {ride? : Bolt ride_id or Sokoni order code (state)} {state? : SEARCHING, DRIVER_ON_ROUTE_TO_CLIENT, ARRIVED_AT_CLIENT, DRIVING_WITH_CLIENT, COMPLETED, NO_DRIVER_FOUND, CANCELLED, CLIENT_DID_NOT_SHOW, PAYMENT_BOOKING_FAILED}';

    protected $description = 'Create, drive or destroy a Bolt Ride Booker sandbox';

    public function handle(BoltClient $bolt, DeliveryJobService $jobs): int
    {
        if (! $bolt->isSandbox()) {
            $this->error('BOLT_ENV is "live". This command only works against the sandbox.');

            return self::FAILURE;
        }

        try {
            return match ($this->argument('action')) {
                'create' => $this->create($bolt),
                'state' => $this->state($bolt, $jobs),
                'destroy' => $this->destroy($bolt),
                default => $this->invalid(),
            };
        } catch (BoltApiException $e) {
            $this->error($e->getMessage());
            foreach ($e->context() as $key => $value) {
                $this->line("  {$key}: ".(is_scalar($value) ? $value : json_encode($value)));
            }

            return self::FAILURE;
        }
    }

    private function create(BoltClient $bolt): int
    {
        $uuid = $bolt->sandboxCreate();
        $this->info('Sandbox created. Add this line to .env, then clear the config cache:');
        $this->line("BOLT_SANDBOX_UUID={$uuid}");

        return self::SUCCESS;
    }

    private function state(BoltClient $bolt, DeliveryJobService $jobs): int
    {
        $ride = (string) $this->argument('ride');
        $state = strtoupper((string) $this->argument('state'));
        if ($ride === '' || $state === '') {
            $this->error('Usage: bolt:sandbox state <ride_id|order code> <STATE>');

            return self::FAILURE;
        }

        $job = DeliveryJob::query()->where('provider_ref', $ride)->first()
            ?? DeliveryJob::query()->whereHas('order', fn ($q) => $q->where('code', strtoupper($ride)))->latest('id')->first();
        $rideId = $job?->provider_ref ?? $ride;

        $bolt->sandboxSetState($rideId, $state);
        $this->info("Ride {$rideId} is now {$state} at Bolt.");

        if ($job !== null) {
            $job = $jobs->sync($job->fresh(), force: true);
            $this->info("Sokoni delivery #{$job->id} (order {$job->order->code}) is now: {$job->status}");
        }

        return self::SUCCESS;
    }

    private function destroy(BoltClient $bolt): int
    {
        $uuid = (string) config('delivery.bolt.sandbox_uuid');
        if ($uuid === '') {
            $this->error('BOLT_SANDBOX_UUID is not set.');

            return self::FAILURE;
        }
        $bolt->sandboxDestroy($uuid);
        $this->info("Sandbox {$uuid} destroyed. Remove BOLT_SANDBOX_UUID from .env.");

        return self::SUCCESS;
    }

    private function invalid(): int
    {
        $this->error('Action must be create, state or destroy.');

        return self::FAILURE;
    }
}
