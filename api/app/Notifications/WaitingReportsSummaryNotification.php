<?php

namespace App\Notifications;

use App\Models\Report;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

/** The daily list of reports still pending after 20 hours, sent by `reports:waiting-summary`. */
class WaitingReportsSummaryNotification extends Notification
{
    /** @param  Collection<int, Report>  $reports */
    public function __construct(private readonly Collection $reports, private readonly int $hours) {}

    public function via(mixed $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        $overdue = $this->reports->filter(fn (Report $r) => $r->isOverdue())->count();

        $message = (new MailMessage)
            ->subject("[Sokoni] {$this->reports->count()} report(s) waiting over {$this->hours} hours".($overdue ? ", {$overdue} overdue" : ''))
            ->greeting('Reports waiting for a decision')
            ->line("These reports have waited more than {$this->hours} hours. Each must be acted on within 24 hours of being filed: hide or remove the content, and ban the user who posted it, or dismiss the report.");

        foreach ($this->reports as $report) {
            $waited = (int) $report->created_at->diffInHours(now());
            $message->line(sprintf(
                '%s **#%d** %s (waited %dh, due %s)',
                $report->isOverdue() ? '🔴' : '🟠',
                $report->id,
                $report->summaryLine(),
                $waited,
                ModerationTime::format($report->dueAt()),
            ));
        }

        return $message->action('Open the Reports queue', route('filament.admin.resources.reports.index'));
    }
}
