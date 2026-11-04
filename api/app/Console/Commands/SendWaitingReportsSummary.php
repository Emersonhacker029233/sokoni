<?php

namespace App\Console\Commands;

use App\Models\Report;
use App\Services\Moderation\ModerationAlerts;
use Illuminate\Console\Command;

/**
 * Once a day (routes/console.php): emails the moderators, and rings the
 * admin-panel bell, with every report still pending after 20 hours, so
 * nothing slips past the 24-hour promise made to Apple (Guideline 1.2).
 * Sends nothing when the queue has no such report.
 */
class SendWaitingReportsSummary extends Command
{
    protected $signature = 'reports:waiting-summary {--hours=20 : Include pending reports at least this old}';

    protected $description = 'Email moderators a summary of reports still pending after 20 hours';

    public function handle(ModerationAlerts $alerts): int
    {
        $hours = max(1, (int) $this->option('hours'));

        $reports = Report::query()
            ->waitingAtLeast($hours)
            ->with(['reporter', 'reportable'])
            ->oldest()
            ->get();

        if ($reports->isEmpty()) {
            $this->info("No report has waited {$hours} hours.");

            return self::SUCCESS;
        }

        $alerts->waitingSummary($reports, $hours);
        $this->info("Sent the summary of {$reports->count()} report(s) waiting over {$hours} hours.");

        return self::SUCCESS;
    }
}
