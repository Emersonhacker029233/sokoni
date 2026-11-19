<?php

namespace App\Services\Delivery\Bolt;

use Illuminate\Http\Client\Response;
use RuntimeException;
use Throwable;

/**
 * A failed call to the Bolt Ride Booker API, carrying the three headers
 * Bolt asks partners to quote in support tickets (x-bolt-error-code,
 * x-bolt-error-message, x-bolt-tracking) and any 400 validation errors.
 *
 * Bolt documents its error codes by name (RIDE_BOOKER_API_AREA_NOT_SERVICED,
 * ...) but says the header holds "a specific number". `is()` therefore
 * matches a name in the code header OR the message header, so the checks
 * keep working whichever form a given response uses.
 */
class BoltApiException extends RuntimeException
{
    /** HTTP statuses Bolt says to retry with backoff. */
    public const TRANSIENT_STATUSES = [429, 500, 502, 503, 504];

    public function __construct(
        string $message,
        public readonly string $operation,
        public readonly ?int $status = null,
        public readonly ?string $errorCode = null,
        public readonly ?string $errorMessage = null,
        public readonly ?string $tracking = null,
        public readonly array $validationErrors = [],
        ?Throwable $previous = null,
        // Seconds from Bolt's Retry-After header (429/503), when it sent one.
        public readonly ?int $retryAfter = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public static function fromResponse(string $operation, Response $response): self
    {
        $code = $response->header('x-bolt-error-code') ?: null;
        $message = $response->header('x-bolt-error-message') ?: null;
        $validation = $response->json('validation_errors');
        $retryAfter = $response->header('Retry-After');

        return new self(
            "Bolt {$operation} failed with HTTP {$response->status()}".($code ? " ({$code})" : '').($message ? ": {$message}" : ''),
            $operation,
            $response->status(),
            $code,
            $message,
            $response->header('x-bolt-tracking') ?: null,
            is_array($validation) ? $validation : [],
            retryAfter: is_numeric($retryAfter) ? (int) $retryAfter : null,
        );
    }

    public static function connection(string $operation, Throwable $previous): self
    {
        return new self("Bolt {$operation} failed: could not connect ({$previous->getMessage()})", $operation, previous: $previous);
    }

    public static function malformed(string $operation, string $detail): self
    {
        return new self("Bolt {$operation} returned an unexpected response: {$detail}", $operation);
    }

    public static function notConfigured(string $operation): self
    {
        return new self("Bolt {$operation} skipped: BOLT_CLIENT_ID/BOLT_CLIENT_SECRET (and BOLT_SANDBOX_UUID in sandbox) are not set", $operation);
    }

    /** True when Bolt's error code or message names one of $codes, e.g. 'AREA_NOT_SERVICED'. */
    public function is(string ...$codes): bool
    {
        $haystack = strtoupper(($this->errorCode ?? '').' '.($this->errorMessage ?? ''));
        foreach ($codes as $code) {
            if ($code !== '' && str_contains($haystack, strtoupper($code))) {
                return true;
            }
        }

        return false;
    }

    /** Worth trying again later: no connection, rate limited, a Bolt-side 5xx, or one of Bolt's "retry after backoff" codes. */
    public function isTransient(): bool
    {
        return ($this->status === null && $this->previous() !== null)
            || in_array($this->status, self::TRANSIENT_STATUSES, true)
            || $this->is('INTERNAL_ORDER_CREATION_FAILED', 'UNABLE_TO_LOCATE_DRIVER', 'FARE_BREAKDOWN_UNAVAILABLE');
    }

    /** Bolt does not serve the pickup point at all: no point retrying. */
    public function isNotServiced(): bool
    {
        return $this->is('AREA_NOT_SERVICED', 'NO_MATCHING_CITY', 'NO_MATCHING_ADDRESS_FOR_PICKUP_COORDINATE');
    }

    /** The fare_id expired or was already used: estimate again for a fresh one. */
    public function isFareRejected(): bool
    {
        return $this->is('INVALID_FARE_ID', 'FARE_ID_DECRYPTION_ERROR');
    }

    /** Bolt can't charge Sokoni's Bolt Business account (overdue invoice, payment method). An admin must act. */
    public function isAccountProblem(): bool
    {
        return in_array($this->status, [401, 403], true) || $this->is('INVALID_PAYMENT_METHOD');
    }

    /** For logs and the delivery timeline. Never contains credentials or tokens. */
    public function context(): array
    {
        return array_filter([
            'operation' => $this->operation,
            'status' => $this->status,
            'x-bolt-error-code' => $this->errorCode,
            'x-bolt-error-message' => $this->errorMessage,
            'x-bolt-tracking' => $this->tracking,
            'validation_errors' => $this->validationErrors ?: null,
        ], fn ($v) => $v !== null);
    }

    private function previous(): ?Throwable
    {
        return $this->getPrevious();
    }
}
