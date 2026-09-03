<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** C5: sent to whichever party didn't trigger the change — mirrors the existing push notification. */
class OrderStatusUpdatedNotification extends Notification
{
    public function __construct(private readonly Order $order, private readonly string $status) {}

    public function via(mixed $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        $sw = ($notifiable->locale ?? 'en') === 'sw';
        $statusLabel = $this->statusLabel($sw);

        return (new MailMessage)
            ->subject($sw ? "Oda {$this->order->code} imesasishwa - Sokoni" : "Order {$this->order->code} updated - Sokoni")
            ->greeting($sw ? 'Habari '.$notifiable->name.',' : 'Hi '.$notifiable->name.',')
            ->line($sw
                ? "Hali ya oda yako {$this->order->code} sasa ni: {$statusLabel}."
                : "Your order {$this->order->code} is now: {$statusLabel}.")
            ->line($sw
                ? 'Fungua programu ya Sokoni kuona maelezo kamili.'
                : 'Open the Sokoni app to see the full details.');
    }

    private function statusLabel(bool $sw): string
    {
        return match ($this->status) {
            'accepted' => $sw ? 'Imekubaliwa' : 'Accepted',
            'ready' => $sw ? 'Iko tayari' : 'Ready',
            'completed' => $sw ? 'Imekamilika' : 'Completed',
            'cancelled' => $sw ? 'Imeghairiwa' : 'Cancelled',
            default => $this->status,
        };
    }
}
