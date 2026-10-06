<?php

namespace App\Services\Sms;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Sends OTPs via Kibonet (https://sms.kibonet.co.tz) — Sokoni's active SMS
 * provider as of the 2026-10 swap from Textify. Bound in AppServiceProvider
 * whenever `SMS_DRIVER=kibonet`.
 *
 * Kibonet's `contacts` field is a comma-separated string rather than a JSON
 * array, so `send()` takes a list of numbers and joins it — every current
 * caller (sendOtp, and the bulk-SMS command's own per-recipient loop) still
 * only ever passes one number, but the join means a future caller that
 * wants one call per batch doesn't need a second code path.
 *
 * Kibonet's docs don't state a number format, so `KIBONET_NUMBER_FORMAT`
 * controls the boundary conversion (default `255`, no leading `+`, the
 * common convention among the Tanzanian panels this provider belongs to)
 * — both the original and converted number are always logged so a
 * mis-format is visible immediately rather than discovered from a support
 * ticket. Kibonet's docs also show no response body at all, so unlike
 * Textify (which treats a missing `success` field as malformed) a 2xx with
 * no recognisable success/status field is read as success; only an
 * *explicit* failure value — on any HTTP status — is treated as a failure.
 * Every request and response is logged to the dedicated `sms` channel so a
 * delivery problem is diagnosable from cPanel File Manager alone.
 */
class KibonetSmsGateway implements SmsGateway
{
    public function __construct(
        private readonly string $apiKey,
        private readonly string $apiSecret,
        private readonly string $senderId,
        private readonly string $endpoint,
        private readonly string $numberFormat,
        private readonly ?string $deliveryReportUrl = null,
    ) {}

    public function sendOtp(string $phone, string $code, string $locale = 'en'): void
    {
        $this->send([$phone], $this->messageFor($code, $locale), 'OTP');
    }

    /** The bulk-SMS admin tool's generic send path — same transport, same failure handling, arbitrary body. */
    public function sendMessage(string $phone, string $body): void
    {
        $this->send([$phone], $body, 'bulk message');
    }

    /**
     * @param array<int, string> $phones E.164 numbers. Kibonet's `contacts`
     *   field takes a comma-separated string, so this accepts a list and
     *   joins it rather than only ever handling one number — see the class
     *   docblock.
     */
    private function send(array $phones, string $message, string $kind): void
    {
        $converted = array_map(fn (string $phone): string => $this->toKibonetFormat($phone), $phones);
        $contacts = implode(',', $converted);

        $payload = [
            'senderId' => $this->senderId,
            'messageType' => 'text',
            'message' => $message,
            'contacts' => $contacts,
        ];

        // Omit entirely when unset rather than send a dead callback URL.
        if ($this->deliveryReportUrl !== null && $this->deliveryReportUrl !== '') {
            $payload['deliveryReportUrl'] = $this->deliveryReportUrl;
        }

        Log::channel('sms')->info("Kibonet: sending {$kind}", [
            'endpoint' => $this->endpoint,
            'original_phones' => $phones,
            'converted_to' => $converted,
            'sender_id' => $this->senderId,
        ]);

        $response = Http::withHeaders([
            'api_key' => $this->apiKey,
            'api_secret' => $this->apiSecret,
        ])->asJson()->acceptJson()->post($this->endpoint, $payload);

        Log::channel('sms')->info('Kibonet: response received', [
            'contacts' => $contacts,
            'http_status' => $response->status(),
            'body' => $response->body(),
        ]);

        if ($response->failed()) {
            $errorMessage = $this->extractMessage($response->json()) ?? ($response->body() ?: 'HTTP '.$response->status());

            Log::channel('sms')->error('Kibonet: SMS not sent — HTTP failure', [
                'contacts' => $contacts,
                'http_status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new RuntimeException("Kibonet SMS to {$contacts} failed: {$errorMessage}");
        }

        $body = $response->json();
        $failureMessage = $this->explicitFailureMessage($body);

        if ($failureMessage !== null) {
            Log::channel('sms')->error('Kibonet: SMS not sent — provider reported failure', [
                'contacts' => $contacts,
                'http_status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new RuntimeException("Kibonet SMS to {$contacts} failed: {$failureMessage}");
        }

        // A 2xx with no explicit failure signal is success — Kibonet's docs
        // show no response body at all, so an empty or field-less body here
        // is the expected shape, not a malformed one (unlike Textify).
    }

    /**
     * Only an explicit failure value is treated as a failure — a 2xx with
     * no `success`/`status` field, or one whose value isn't recognisably
     * negative, is left as a success per the class docblock.
     *
     * @param mixed $body
     */
    private function explicitFailureMessage(mixed $body): ?string
    {
        if (! is_array($body)) {
            return null;
        }

        if (array_key_exists('success', $body) && $body['success'] === false) {
            return $this->extractMessage($body) ?? 'Kibonet reported failure';
        }

        if (array_key_exists('status', $body) && is_string($body['status'])
            && in_array(strtolower($body['status']), ['failed', 'failure', 'error'], true)) {
            return $this->extractMessage($body) ?? 'Kibonet reported failure';
        }

        return null;
    }

    /** @param mixed $body */
    private function extractMessage(mixed $body): ?string
    {
        if (! is_array($body)) {
            return null;
        }

        return $body['message'] ?? $body['error'] ?? null;
    }

    /**
     * The app stores E.164 (`+255XXXXXXXXX`); Kibonet's docs don't state a
     * format at all, so `KIBONET_NUMBER_FORMAT` (default `255`, no leading
     * `+`) picks the convention at the boundary without needing a redeploy
     * if it turns out wrong. The caller logs both the original and
     * converted number either way, so a wrong shape is visible in the `sms`
     * channel immediately, not just when it happens to fail.
     */
    private function toKibonetFormat(string $phone): string
    {
        $trimmed = trim($phone);

        if (str_starts_with($trimmed, '+255')) {
            $local = substr($trimmed, 4);
        } elseif (str_starts_with($trimmed, '255')) {
            $local = substr($trimmed, 3);
        } elseif (str_starts_with($trimmed, '0')) {
            $local = substr($trimmed, 1);
        } else {
            // Unrecognised shape — pass the digits through as-is rather
            // than guess wrong; the logged original/converted pair makes
            // it obvious.
            $local = $trimmed;
        }

        return match ($this->numberFormat) {
            '0' => '0'.$local,
            '+255' => '+255'.$local,
            default => '255'.$local,
        };
    }

    private function messageFor(string $code, string $locale): string
    {
        return $locale === 'sw'
            ? "Namba yako ya Sokoni: {$code}. Usimpe mtu yeyote."
            : "Your Sokoni code: {$code}. Do not share it.";
    }
}
