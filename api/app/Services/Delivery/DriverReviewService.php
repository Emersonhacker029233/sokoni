<?php

namespace App\Services\Delivery;

use App\Models\DeliveryJob;
use App\Models\DeliveryOffer;
use App\Models\DriverProfile;
use App\Models\User;
use App\Support\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Every admin decision on a Sokoni Driver account, in one place, the same
 * way SellerVerificationService does it for shops: change the state, write
 * the activity log entry naming the admin, and tell the driver
 * (DriverAccountMessages: in-app record, push or SMS fallback).
 *
 * Each method re-reads the driver under a row lock, so two admins clicking
 * at once can't both apply a decision to a stale state.
 */
class DriverReviewService
{
    public function __construct(
        private readonly DriverAccountMessages $messages,
        private readonly DeliveryStateMachine $stateMachine,
        private readonly DeliveryMessages $deliveryMessages,
    ) {}

    /** Submitted and waiting, or approved with a new vehicle nobody has checked yet. */
    public static function needsReview(DriverProfile $driver): bool
    {
        return self::isApplication($driver) || self::isVehicleCheck($driver);
    }

    public static function isApplication(DriverProfile $driver): bool
    {
        return $driver->status === 'pending' && $driver->submitted_at !== null;
    }

    public static function isVehicleCheck(DriverProfile $driver): bool
    {
        return $driver->status === 'approved'
            && $driver->activeVehicle !== null
            && $driver->activeVehicle->verified_at === null;
    }

    /**
     * Approves a waiting application, or verifies the new vehicle of an
     * already approved driver. Either way the active vehicle ends up verified.
     *
     * @return string 'approved' or 'vehicle_approved'
     */
    public function approve(DriverProfile $driver, User $admin): string
    {
        [$event, $vehicle] = DB::transaction(function () use ($driver, $admin) {
            $fresh = $this->lock($driver);
            $isApplication = self::isApplication($fresh);
            if (! $isApplication && ! self::isVehicleCheck($fresh)) {
                throw new DriverReviewException('This driver has nothing waiting for approval.');
            }
            $missing = $fresh->missingRequirements();
            if ($missing !== []) {
                throw new DriverReviewException('Cannot approve yet. Missing: '.implode(', ', $missing).'.');
            }
            if ($fresh->licence_expires_on !== null && $fresh->licence_expires_on->isPast()) {
                throw new DriverReviewException('The driving licence expired on '.$fresh->licence_expires_on->toDateString().'. Ask the driver to upload a valid one.');
            }

            if ($isApplication) {
                $fresh->forceFill([
                    'status' => 'approved',
                    'approved_at' => now(),
                    'approved_by' => $admin->id,
                    'rejection_reason' => null,
                    'suspended_reason' => null,
                ])->save();
            }
            $vehicle = $fresh->activeVehicle;
            $vehicle?->forceFill(['verified_at' => now()])->save();

            return [$isApplication ? 'approved' : 'vehicle_approved', $vehicle];
        });

        $channels = $this->messages->send($driver->fresh(), $event);
        ActivityLogger::record($admin, $event === 'approved' ? 'driver.approved' : 'driver.vehicle_verified', $driver, null, [
            'vehicle_id' => $vehicle?->id,
            'plate_number' => $vehicle?->plate_number,
            'notified_by' => $channels,
        ]);

        return $event;
    }

    public function reject(DriverProfile $driver, User $admin, string $reason): void
    {
        $reason = $this->cleanReason($reason);

        DB::transaction(function () use ($driver, $reason) {
            $fresh = $this->lock($driver);
            if (! self::needsReview($fresh)) {
                throw new DriverReviewException('Only an application or vehicle waiting for review can be rejected.');
            }
            // The app shows this reason on its status screen, next to "Fix and resubmit".
            $fresh->forceFill([
                'status' => 'rejected',
                'rejection_reason' => $reason,
                'is_online' => false,
            ])->save();
        });

        $channels = $this->messages->send($driver->fresh(), 'rejected', $reason);
        ActivityLogger::record($admin, 'driver.rejected', $driver, $reason, ['notified_by' => $channels]);
    }

    public function suspend(DriverProfile $driver, User $admin, string $reason): void
    {
        $reason = $this->cleanReason($reason);

        DB::transaction(function () use ($driver, $reason) {
            $fresh = $this->lock($driver);
            if ($fresh->status !== 'approved') {
                throw new DriverReviewException('Only an approved driver can be suspended.');
            }
            // A suspended driver is refused on every work route, so they
            // could not finish a delivery already in their hands.
            $active = $fresh->activeJob();
            if ($active !== null) {
                throw new DriverReviewException("This driver is on delivery #{$active->id} ({$active->status}). Suspend them once it has finished.");
            }
            $fresh->forceFill([
                'status' => 'suspended',
                'suspended_reason' => $reason,
                'is_online' => false,
            ])->save();
            // Open offers can no longer be accepted; mark them so the record is honest.
            DeliveryOffer::query()
                ->where('driver_profile_id', $fresh->id)
                ->where('status', 'offered')
                ->update(['status' => 'withdrawn', 'responded_at' => now()]);
        });

        $channels = $this->messages->send($driver->fresh(), 'suspended', $reason);
        ActivityLogger::record($admin, 'driver.suspended', $driver, $reason, ['notified_by' => $channels]);
    }

    public function reinstate(DriverProfile $driver, User $admin): void
    {
        DB::transaction(function () use ($driver) {
            $fresh = $this->lock($driver);
            if ($fresh->status !== 'suspended') {
                throw new DriverReviewException('Only a suspended driver can be reinstated.');
            }
            $fresh->forceFill(['status' => 'approved', 'suspended_reason' => null])->save();
        });

        $channels = $this->messages->send($driver->fresh(), 'reinstated');
        ActivityLogger::record($admin, 'driver.reinstated', $driver, null, ['notified_by' => $channels]);
    }

    /**
     * After 5 wrong customer PINs the job locks (DeliveryJobService::checkPin)
     * and support is alerted. Once support has confirmed the hand-over with
     * the customer, this lets the driver try again. The PIN itself is kept:
     * the customer already has it in their app.
     */
    public function unlockPin(DeliveryJob $job, User $admin): void
    {
        $fresh = DB::transaction(function () use ($job, $admin) {
            /** @var DeliveryJob $fresh */
            $fresh = DeliveryJob::query()->lockForUpdate()->findOrFail($job->id);
            if ($fresh->pin_locked_at === null) {
                throw new DriverReviewException('Delivery PIN entry is not locked on this job.');
            }
            if (! $fresh->isDriverActive()) {
                throw new DriverReviewException('This delivery is no longer in progress.');
            }
            $attempts = $fresh->pin_attempts;
            $fresh->forceFill(['pin_attempts' => 0, 'pin_locked_at' => null])->save();
            $this->stateMachine->record($fresh, $admin, 'admin', 'pin_unlocked', meta: ['attempts_before' => $attempts]);

            return $fresh;
        });

        $this->deliveryMessages->pinUnlocked($fresh);
        ActivityLogger::record($admin, 'delivery.pin_unlocked', $fresh, null, [
            'order_id' => $fresh->order_id,
            'driver_profile_id' => $fresh->driver_profile_id,
        ]);
    }

    private function lock(DriverProfile $driver): DriverProfile
    {
        /** @var DriverProfile $fresh */
        $fresh = DriverProfile::query()->lockForUpdate()->with('activeVehicle')->findOrFail($driver->id);

        return $fresh;
    }

    /** Required, trimmed, and short enough for the 255-character column. */
    private function cleanReason(string $reason): string
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw new DriverReviewException('A reason is required.');
        }

        return Str::limit($reason, 250, '');
    }
}
