<?php

namespace App\Notifications;

use App\Models\Review;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** C5: sent to the seller when a buyer leaves a review — no push equivalent existed before this either. */
class ReviewReceivedNotification extends Notification
{
    public function __construct(private readonly Review $review) {}

    public function via(mixed $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        $sw = ($notifiable->locale ?? 'en') === 'sw';
        $stars = str_repeat('★', $this->review->rating).str_repeat('☆', 5 - $this->review->rating);

        $message = (new MailMessage)
            ->subject($sw ? 'Umepata tathmini mpya - Sokoni' : 'You received a new review - Sokoni')
            ->greeting($sw ? 'Habari '.$notifiable->name.',' : 'Hi '.$notifiable->name.',')
            ->line($sw
                ? "Duka lako limepata tathmini mpya: {$stars}"
                : "Your shop received a new review: {$stars}");

        if ($this->review->comment) {
            $message->line('"'.$this->review->comment.'"');
        }

        return $message->line($sw
            ? 'Fungua programu ya Sokoni kujibu tathmini hii.'
            : 'Open the Sokoni app to reply to this review.');
    }
}
