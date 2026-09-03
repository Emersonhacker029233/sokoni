<?php

namespace App\Services\Sms;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Sends OTPs via Africa's Talking's SMS API — Sokoni's real, contracted SMS
 * provider (dashboard: https://account.africastalking.com/apps/gladxtazoy,
 * app "skyfar"). Bound in AppServiceProvider whenever `SMS_DRIVER=africastalking`.
 *
 * HTTP 201 from this API means "the request was accepted for processing",
 * not "delivered" — the actual per-recipient outcome is a `status`/
 * `statusCode` pair inside the response body (see docs/SMS.md for the
 * exact shape). Every send is logged to the dedicated `sms` channel
 * (request and response) so a failure is diagnosable from cPanel File
 * Manager alone, with no shell access needed.
 */
class AfricasTalkingSmsGateway implements SmsGateway
{
    private const LIVE_ENDPOINT = 'https://api.africastalking.com/version1/messaging';
    private const SANDBOX_ENDPOINT = 'https://api.sandbox.africastalking.com/version1/messaging';

    /** Africa's Talking's own status strings that mean the message didn't go out for lack of funds. */
    private const INSUFFICIENT_BALANCE_STATUSES = ['InsufficientBalance'];

    public function __construct(
        private readonly string $username,
        private readonly string $apiKey,
        private readonly string $senderId,
        private readonly bool $sandbox = false,
    ) {}

    public function sendOtp(string $phone, string $code, string $locale = 'en'): void
    {
        $this->send($phone, $this->messageFor($code, $locale), 'OTP');
    }

    /** C6: the bulk-SMS admin tool's generic send path — same transport, same failure handling, arbitrary body. */
    public function sendMessage(string $phone, string $body): void
    {
        $this->send($phone, $body, 'bulk message');
    }

    private function send(string $phone, string $message, string $kind): void
    {
        $to = $this->normalizePhone($phone);
        $endpoint = $this->sandbox ? self::SANDBOX_ENDPOINT : self::LIVE_ENDPOINT;

        Log::channel('sms')->info("Africa's Talking: sending {$kind}", [
            'endpoint' => $endpoint,
            'to' => $to,
            'from' => $this->senderId,
            'sandbox' => $this->sandbox,
        ]);

        $response = Http::asForm()
            ->withHeaders(['apiKey' => $this->apiKey, 'Accept' => 'application/json'])
            ->post($endpoint, [
                'username' => $this->username,
                'to' => $to,
                'message' => $message,
                'from' => $this->senderId,
            ]);

        Log::channel('sms')->info('Africa\'s Talking: response received', [
            'to' => $to,
            'http_status' => $response->status(),
            'body' => $response->body(),
        ]);

        if ($response->failed()) {
            throw new RuntimeException(
                "Africa's Talking SMS request failed with HTTP {$response->status()}: {$response->body()}"
            );
        }

        $recipients = $response->json('SMSMessageData.Recipients') ?? [];
        if (empty($recipients)) {
            // HTTP 201 with no recipients at all is itself a malformed/
            // unexpected response — never silently treat "no news" as success.
            Log::channel('sms')->error('Africa\'s Talking: HTTP 201 but no Recipients in the response body', [
                'to' => $to,
                'body' => $response->body(),
            ]);

            throw new RuntimeException("Africa's Talking SMS response to {$to} had no recipients: {$response->body()}");
        }

        foreach ($recipients as $recipient) {
            $status = $recipient['status'] ?? 'Unknown';
            if ($status === 'Success') {
                continue;
            }

            $statusCode = $recipient['statusCode'] ?? 'unknown';
            $number = $recipient['number'] ?? $to;

            if (in_array($status, self::INSUFFICIENT_BALANCE_STATUSES, true)) {
                // The single most common production failure — must be loud,
                // never buried at the same level as a routine send.
                Log::channel('sms')->critical('Africa\'s Talking: INSUFFICIENT BALANCE — SMS not sent', [
                    'to' => $number,
                    'status' => $status,
                    'statusCode' => $statusCode,
                ]);

                throw new RuntimeException(
                    "Africa's Talking SMS to {$number} failed: account has insufficient balance (status {$status}, code {$statusCode})."
                );
            }

            Log::channel('sms')->error('Africa\'s Talking: SMS not delivered', [
                'to' => $number,
                'status' => $status,
                'statusCode' => $statusCode,
            ]);

            throw new RuntimeException("Africa's Talking SMS to {$number} failed: {$status} (code {$statusCode}).");
        }
    }

    /**
     * The app already stores phone numbers in E.164, but this is the
     * boundary to an external API — defend against a stray missing `+`
     * rather than trust every caller got there correctly.
     */
    private function normalizePhone(string $phone): string
    {
        $trimmed = trim($phone);

        return str_starts_with($trimmed, '+') ? $trimmed : '+'.ltrim($trimmed, '+');
    }

    private function messageFor(string $code, string $locale): string
    {
        return $locale === 'sw'
            ? "Namba yako ya Sokoni: {$code}. Usimpe mtu yeyote."
            : "Your Sokoni code: {$code}. Do not share it.";
    }
}
