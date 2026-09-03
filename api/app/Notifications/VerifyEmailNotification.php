<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail as BaseVerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Bilingual override of the framework's default `VerifyEmail` — the
 * signed-URL generation (expiry, `verification.verify` route, id/hash
 * params) is inherited unchanged; only the mail content differs, chosen
 * from the recipient's own `locale` (CLAUDE.md: "never hardcode a
 * user-facing string").
 */
class VerifyEmailNotification extends BaseVerifyEmail
{
    public function toMail(mixed $notifiable): MailMessage
    {
        $url = $this->verificationUrl($notifiable);
        $sw = ($notifiable->locale ?? 'en') === 'sw';

        return (new MailMessage)
            ->subject($sw ? 'Thibitisha barua pepe yako - Sokoni' : 'Verify your email - Sokoni')
            ->greeting($sw ? 'Habari '.$notifiable->name.',' : 'Hi '.$notifiable->name.',')
            ->line($sw
                ? 'Bonyeza kitufe hapa chini kuthibitisha barua pepe yako ya akaunti yako ya Sokoni.'
                : 'Click the button below to verify your email address for your Sokoni account.')
            ->action($sw ? 'Thibitisha barua pepe' : 'Verify email', $url)
            ->line($sw
                ? 'Kama hukufungua akaunti hii, hakuna hatua nyingine inayohitajika.'
                : "If you didn't create this account, no further action is required.");
    }
}
