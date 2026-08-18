<?php

namespace App\Observers;

use App\Models\Report;
use App\Support\Settings;
use Illuminate\Database\Eloquent\Model;

/** Upheld reports against the same content, at/above the configured threshold, auto-hide it pending review. */
class ReportObserver
{
    public function updated(Report $report): void
    {
        if (! $report->wasChanged('status') || $report->status !== 'upheld') {
            return;
        }

        $upheldCount = Report::query()
            ->where('reportable_type', $report->reportable_type)
            ->where('reportable_id', $report->reportable_id)
            ->where('status', 'upheld')
            ->count();

        if ($upheldCount < Settings::reportAutoHideThreshold()) {
            return;
        }

        $reportable = $report->reportable;
        if ($reportable instanceof Model && array_key_exists('is_hidden', $reportable->getAttributes())) {
            $reportable->forceFill(['is_hidden' => true])->save();
        }
    }
}
