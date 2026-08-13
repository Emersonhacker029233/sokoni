<?php

namespace App\Observers;

use App\Models\Report;
use Illuminate\Database\Eloquent\Model;

/** Three upheld reports against the same content auto-hide it pending review. */
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

        if ($upheldCount < Report::AUTO_HIDE_THRESHOLD) {
            return;
        }

        $reportable = $report->reportable;
        if ($reportable instanceof Model && array_key_exists('is_hidden', $reportable->getAttributes())) {
            $reportable->forceFill(['is_hidden' => true])->save();
        }
    }
}
