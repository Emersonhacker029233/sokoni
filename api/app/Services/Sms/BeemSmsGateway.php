<?php

namespace App\Services\Sms;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Sends OTPs via Beem Africa's SMS API (https://apisms.beem.africa/v1/send) —
 * the default recommendation for Tanzania-only traffic (see docs/SMS.md).
 * Bound in AppServiceProvider only when both `BEEM_SMS_API_KEY` and
 * `BEEM_SMS_SECRET_KEY` are set; otherwise the container falls back to
 * LogSmsGateway, so local dev and CI never need real credentials.
 */
class BeemSmsGateway implements SmsGateway
{
    private const ENDPOINT = 'https://apisms.beem.africa/v1/send';

    public function __construct(
        private readonly string $apiKey,
        private readonly string $secretKey,
        private readonly string $senderId,
    ) {}

    public function sendOtp(string $phone, string $code, string $locale = 'en'): void
    {
        $this->send($phone, "Your Sokoni verification code is {$code}. It expires in 5 minutes.");
    }

    /** C6: the bulk-SMS admin tool's generic send path — same transport, same failure handling, arbitrary body. */
    public function sendMessage(string $phone, string $body): void
    {
        $this->send($phone, $body);
    }

    private function send(string $phone, string $message): void
    {
        // Beem expects a bare MSISDN (no leading +) — E.164 minus the plus.
        $destAddr = ltrim($phone, '+');

        $response = Http::withBasicAuth($this->apiKey, $this->secretKey)
            ->acceptJson()
            ->post(self::ENDPOINT, [
                'source_addr' => $this->senderId,
                'encoding' => 0,
                'message' => $message,
                'recipients' => [
                    ['recipient_id' => 1, 'dest_addr' => $destAddr],
                ],
            ]);

        if ($response->failed() || $response->json('successful') !== true) {
            throw new RuntimeException(
                'Beem SMS send failed: '.($response->json('message') ?? $response->body())
            );
        }
    }
}
