<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** C5: sent to the seller alongside the existing push notification — order-placed email. */
class OrderPlacedNotification extends Notification
{
    public function __construct(private readonly Order $order) {}

    public function via(mixed $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        $sw = ($notifiable->locale ?? 'en') === 'sw';
        $total = 'TSh '.number_format((float) $this->order->total);

        return (new MailMessage)
            ->subject($sw ? "Oda mpya {$this->order->code} - Sokoni" : "New order {$this->order->code} - Sokoni")
            ->greeting($sw ? 'Habari '.$notifiable->name.',' : 'Hi '.$notifiable->name.',')
            ->line($sw
                ? "Umepata oda mpya yenye thamani ya {$total}."
                : "You have a new order worth {$total}.")
            ->line($sw ? "Namba ya oda: {$this->order->code}" : "Order number: {$this->order->code}")
            ->line($sw
                ? 'Fungua programu ya Sokoni kuona maelezo kamili na kuwasiliana na mnunuzi.'
                : 'Open the Sokoni app to see the full details and message the buyer.');
    }
}
