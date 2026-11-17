<?php

namespace App\Notifications;

use App\Models\Report;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/** Sent to MODERATION_ALERT_EMAIL for every new report or block (Apple Guideline 1.2: act within 24 hours). */
class NewReportNotification extends Notification
{
    public function __construct(private readonly Report $report) {}

    public function via(mixed $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        $report = $this->report;
        $preview = $report->contentPreview();
        $author = $report->offendingUser();
        $isBlock = $report->reason === 'blocked';

        $message = (new MailMessage)
            ->subject(($isBlock ? '[Sokoni] User blocked: ' : '[Sokoni] New report: ').ucfirst($report->typeName()).' — '.$report->reasonLabel())
            ->greeting($isBlock ? 'A user was blocked' : 'New report to review')
            ->line('**Type:** '.ucfirst($report->typeName()).' #'.$report->reportable_id)
            ->line('**Reason:** '.$report->reasonLabel())
            ->line('**Reported by:** '.($report->reporter?->name ?? 'unknown').' (user #'.$report->reporter_id.')')
            ->line('**Posted by:** '.($author ? $author->name.' (user #'.$author->id.')' : 'unknown'))
            ->line('**Content:** '.$preview['title']);

        if ($preview['text'] !== null) {
            $message->line('> '.Str::limit(str_replace("\n", ' ', $preview['text']), 400));
        }
        if ($report->note) {
            $message->line('**Note from reporter:** '.Str::limit($report->note, 400));
        }

        return $message
            ->line('**Act by:** '.ModerationTime::format($report->dueAt()).' (24 hours after it was filed).')
            ->action('Open the report', route('filament.admin.resources.reports.view', $report));
    }
}
