<?php

namespace App\Services\Moderation;

use App\Models\Report;
use App\Models\User;
use App\Notifications\NewReportNotification;
use App\Notifications\WaitingReportsSummaryNotification;
use App\Support\SafeMail;
use Filament\Actions\Action;
use Filament\Notifications\Notification as PanelNotification;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification as NotificationRoute;
use Throwable;

/**
 * Tells the moderators (Apple Guideline 1.2: "act on objectionable content
 * reports within 24 hours"): an email to MODERATION_ALERT_EMAIL and a bell
 * notification for every admin-panel user. Neither may ever fail the
 * report or block that triggered it, so both swallow and log their own
 * errors (the email via SafeMail, the bell here).
 */
class ModerationAlerts
{
    public function newReport(Report $report): void
    {
        $report->loadMissing(['reporter', 'reportable']);

        $this->email(new NewReportNotification($report));
        $this->bell(
            title: $report->reason === 'blocked' ? 'A user was blocked' : 'New report',
            body: $report->summaryLine(),
            url: route('filament.admin.resources.reports.view', $report),
            urgent: false,
        );

        $report->forceFill(['alerted_at' => now()])->saveQuietly();
    }

    /** @param  Collection<int, Report>  $reports */
    public function waitingSummary(Collection $reports, int $hours): void
    {
        $this->email(new WaitingReportsSummaryNotification($reports, $hours));
        $this->bell(
            title: "{$reports->count()} report(s) waiting over {$hours} hours",
            body: 'Act on each within 24 hours of it being filed.',
            url: route('filament.admin.resources.reports.index'),
            urgent: true,
        );
    }

    /** @return list<string> */
    public function recipients(): array
    {
        return collect(explode(',', (string) config('sokoni.moderation_alert_email')))
            ->map(fn (string $address) => trim($address))
            ->filter(fn (string $address) => filter_var($address, FILTER_VALIDATE_EMAIL) !== false)
            ->values()
            ->all();
    }

    private function email(Notification $notification): void
    {
        $recipients = $this->recipients();
        if ($recipients === []) {
            Log::channel('mail')->warning('No valid MODERATION_ALERT_EMAIL; moderation alert not emailed', [
                'notification' => $notification::class,
            ]);

            return;
        }

        SafeMail::send(NotificationRoute::route('mail', $recipients), $notification);
    }

    private function bell(string $title, string $body, string $url, bool $urgent): void
    {
        try {
            $admins = User::query()->where('is_admin', true)->get();
            if ($admins->isEmpty()) {
                return;
            }

            $notification = PanelNotification::make()
                ->title($title)
                ->body($body)
                ->icon('heroicon-o-flag')
                ->actions([Action::make('open')->label('Open')->url($url)->markAsRead()]);

            ($urgent ? $notification->danger() : $notification->warning())->sendToDatabase($admins);
        } catch (Throwable $e) {
            Log::error('Moderation admin-panel alert failed', ['title' => $title, 'error' => $e->getMessage()]);
        }
    }
}
