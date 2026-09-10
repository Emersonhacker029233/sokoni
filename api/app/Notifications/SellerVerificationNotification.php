<?php

namespace App\Notifications;

use App\Models\SellerProfile;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * B4 (tester feedback): "When an admin approves a shop, the seller learns
 * nothing." Sent to the seller alongside the existing in-app push
 * (SellerProfileResource::verifyAction()/rejectAction()) — same pattern as
 * OrderStatusUpdatedNotification, one class branching on the seller's own
 * current `status` rather than two near-identical classes.
 */
class SellerVerificationNotification extends Notification
{
    public function __construct(private readonly SellerProfile $seller) {}

    public function via(mixed $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        $sw = ($notifiable->locale ?? 'en') === 'sw';

        return $this->seller->status === 'verified'
            ? $this->verifiedMail($sw)
            : $this->rejectedMail($sw);
    }

    private function verifiedMail(bool $sw): MailMessage
    {
        return (new MailMessage)
            ->subject($sw ? 'Duka lako limethibitishwa - Sokoni' : 'Your shop is verified - Sokoni')
            ->greeting($sw ? 'Hongera!' : 'Congratulations!')
            ->line($sw
                ? "Duka lako \"{$this->seller->shop_name}\" limethibitishwa na sasa linaonekana kwenye Sokoni."
                : "Your shop \"{$this->seller->shop_name}\" is verified and now visible on Sokoni.")
            ->line($sw
                ? 'Wanunuzi wanaweza sasa kuona bidhaa zako na kukutafuta moja kwa moja.'
                : 'Buyers can now see your products and find you directly.');
    }

    private function rejectedMail(bool $sw): MailMessage
    {
        return (new MailMessage)
            ->subject($sw ? 'Usajili wa duka lako - Sokoni' : 'Your shop registration - Sokoni')
            ->greeting($sw ? 'Habari,' : 'Hi,')
            ->line($sw
                ? "Kwa bahati mbaya, usajili wa duka lako \"{$this->seller->shop_name}\" haukukubaliwa."
                : "Unfortunately, your shop \"{$this->seller->shop_name}\" registration wasn't approved.")
            ->line(($sw ? 'Sababu: ' : 'Reason: ').$this->seller->rejection_reason)
            ->line($sw
                ? 'Unaweza kusahihisha maelezo yako na kuwasilisha tena.'
                : 'You can correct your details and submit again.');
    }
}
