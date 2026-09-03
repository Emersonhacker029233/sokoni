<?php

namespace App\Services\Sms;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Sends OTPs via Textify Africa (https://docs.textify.africa) — Sokoni's
 * active SMS provider. Bound in AppServiceProvider whenever `SMS_DRIVER=textify`.
 *
 * Textify's API takes numbers in local format (`0712345678`), not E.164 —
 * the app stores `+255...`, so the boundary conversion happens here, and
 * the converted number is always logged so a mis-format is visible rather
 * than discovered from a support ticket. The `success` field in the
 * response body is the real outcome, independent of HTTP status — a 401
 * with `success: false` and a 200 with `success: false` are both failures,
 * and neither is treated as anything else. Every send is logged to the
 * dedicated `sms` channel so a failure is diagnosable from cPanel File
 * Manager alone, with no shell access needed.
 */
class TextifySmsGateway implements SmsGateway
{
    public function __construct(
        private readonly string $apiKey,
        private readonly string $senderName,
        private readonly string $endpoint,
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
        $to = $this->toLocalFormat($phone);

        Log::channel('sms')->info("Textify: sending {$kind}", [
            'endpoint' => $this->endpoint,
            'original_phone' => $phone,
            'converted_to' => $to,
            'sender_name' => $this->senderName,
        ]);

        $response = Http::withToken($this->apiKey)
            ->acceptJson()
            ->asJson()
            ->post($this->endpoint, [
                'sender_name' => $this->senderName,
                'is_scheduled' => false,
                'messages' => [
                    ['receiver' => $to, 'content' => $message],
                ],
            ]);

        Log::channel('sms')->info('Textify: response received', [
            'to' => $to,
            'http_status' => $response->status(),
            'body' => $response->body(),
        ]);

        $body = $response->json();

        if (! is_array($body) || ! array_key_exists('success', $body)) {
            Log::channel('sms')->error('Textify: malformed response — no `success` field present', [
                'to' => $to,
                'http_status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new RuntimeException("Textify SMS response to {$to} was malformed: {$response->body()}");
        }

        if ($body['success'] === true) {
            return;
        }

        $errorMessage = $body['message'] ?? 'unknown error';
        $error = $body['error'] ?? 'unknown_error';

        if ($error === 'invalid_token') {
            // A wrong or revoked API key stops every send until someone
            // notices — must be unmistakable, not buried as a routine failure.
            Log::channel('sms')->critical('Textify: INVALID API KEY — SMS not sent', [
                'to' => $to,
                'http_status' => $response->status(),
                'message' => $errorMessage,
                'error' => $error,
            ]);

            throw new RuntimeException(
                "Textify SMS to {$to} failed: invalid or revoked API key (invalid_token) — {$errorMessage}."
            );
        }

        Log::channel('sms')->error('Textify: SMS not sent', [
            'to' => $to,
            'http_status' => $response->status(),
            'message' => $errorMessage,
            'error' => $error,
        ]);

        throw new RuntimeException("Textify SMS to {$to} failed: {$errorMessage} ({$error}).");
    }

    /**
     * The app stores E.164 (`+255XXXXXXXXX`); Textify's documented examples
     * use local format (`0XXXXXXXXX`). Convert defensively at this boundary
     * rather than assume every caller already normalised correctly — and
     * the caller (sendOtp) logs the result either way so a wrong shape is
     * always visible in the `sms` channel, not just when it happens to fail.
     */
    private function toLocalFormat(string $phone): string
    {
        $trimmed = trim($phone);

        if (str_starts_with($trimmed, '+255')) {
            return '0'.substr($trimmed, 4);
        }

        if (str_starts_with($trimmed, '255')) {
            return '0'.substr($trimmed, 3);
        }

        // Already local, or an unrecognised shape — pass through rather
        // than guess wrong; the logged original/converted pair makes an
        // unexpected input obvious.
        return $trimmed;
    }

    private function messageFor(string $code, string $locale): string
    {
        return $locale === 'sw'
            ? "Namba yako ya Sokoni: {$code}. Usimpe mtu yeyote."
            : "Your Sokoni code: {$code}. Do not share it.";
    }
}
