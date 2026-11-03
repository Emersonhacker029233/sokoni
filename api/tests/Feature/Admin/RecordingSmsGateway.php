<?php

namespace Tests\Feature\Admin;

use App\Services\Sms\SmsGateway;

/** Test double: records SMS instead of sending them. */
class RecordingSmsGateway implements SmsGateway
{
    /** @var list<array{phone: string, body: string}> */
    public array $messages = [];

    public function sendOtp(string $phone, string $code, string $locale = 'en'): void
    {
        $this->messages[] = ['phone' => $phone, 'body' => "OTP {$code}"];
    }

    public function sendMessage(string $phone, string $body): void
    {
        $this->messages[] = ['phone' => $phone, 'body' => $body];
    }
}
