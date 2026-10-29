<?php

namespace App\Services\Delivery;

use App\Models\DeliveryJob;
use App\Models\DriverProfile;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Per-job earnings, period summaries and the running balance a manual
 * payout settles. All amounts whole TZS.
 *
 * Cash-fee jobs (only if delivery_fee_cash_enabled) mean the driver already
 * holds the whole fee, so the platform's commission on them is subtracted
 * from what Sokoni owes the driver. The balance can go negative, meaning the
 * driver owes Sokoni.
 */
class DriverEarnings
{
    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} */
    public static function period(string $period, ?string $date = null): array
    {
        $tz = config('app.timezone');
        $anchor = $date ? CarbonImmutable::parse($date, $tz) : CarbonImmutable::now($tz);

        return $period === 'week'
            ? [$anchor->startOfWeek(), $anchor->endOfWeek()]
            : [$anchor->startOfDay(), $anchor->endOfDay()];
    }

    public function summary(DriverProfile $driver, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $jobs = $this->settledJobs($driver)
            ->filter(fn (DeliveryJob $j) => $this->settledAt($j)->betweenIncluded($from, $to))
            ->values();

        $delivered = $jobs->where('status', 'delivered');

        return [
            'from' => $from->toIso8601String(),
            'to' => $to->toIso8601String(),
            'jobs_count' => $jobs->count(),
            'delivered_count' => $delivered->count(),
            'gross_fees' => (int) $delivered->sum('fee'),
            'commission' => (int) $delivered->sum('commission'),
            'earnings' => (int) $jobs->sum('earned_amount'),
            'cash_collected' => (int) $delivered->where('payment_status', 'cash')->sum('fee'),
            'by_day' => $jobs
                ->groupBy(fn (DeliveryJob $j) => $this->settledAt($j)->toDateString())
                ->map(fn (Collection $day, string $date) => [
                    'date' => $date,
                    'jobs' => $day->count(),
                    'earnings' => (int) $day->sum('earned_amount'),
                ])
                ->sortKeys()
                ->values()
                ->all(),
            'jobs' => $jobs->sortByDesc(fn ($j) => $this->settledAt($j))->values()->map(fn (DeliveryJob $j) => [
                'id' => $j->id,
                'order_code' => $j->order?->code,
                'status' => $j->status,
                'fee' => $j->fee,
                'commission' => $j->status === 'delivered' ? $j->commission : 0,
                'earned_amount' => (int) $j->earned_amount,
                'payment_status' => $j->payment_status,
                'distance_km' => $j->distance_km,
                'settled_at' => $this->settledAt($j)->toIso8601String(),
                'paid_out' => $j->payout_id !== null,
            ])->all(),
        ];
    }

    /** Owed to the driver across every job not yet covered by a payout. */
    public function balance(DriverProfile $driver): array
    {
        $unpaid = $this->settledJobs($driver)->whereNull('payout_id');
        $earned = (int) $unpaid->sum('earned_amount');
        $cash = (int) $unpaid->where('status', 'delivered')->where('payment_status', 'cash')->sum('fee');

        return [
            'unpaid_earnings' => $earned,
            'cash_held' => $cash,
            'balance' => $earned - $cash,
        ];
    }

    /** @return Collection<int, DeliveryJob> */
    private function settledJobs(DriverProfile $driver): Collection
    {
        // earned_amount is only ever set at a terminal outcome (delivered,
        // failed, or cancelled after assignment), and those jobs keep their
        // driver_profile_id, so this one filter covers cancellation fees too.
        return DeliveryJob::query()
            ->with('order:id,code')
            ->where('driver_profile_id', $driver->id)
            ->whereNotNull('earned_amount')
            ->whereIn('status', ['delivered', 'failed', 'cancelled'])
            ->get();
    }

    private function settledAt(DeliveryJob $job): CarbonImmutable
    {
        return CarbonImmutable::parse($job->delivered_at ?? $job->failed_at ?? $job->cancelled_at ?? $job->updated_at);
    }
}
