<?php

namespace App\Services\Delivery;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * A delivery action the caller is not allowed to take right now. Rendered
 * as JSON with a stable machine-readable `code` the apps switch on, plus a
 * localised `message` they can show as-is.
 */
class DeliveryActionException extends RuntimeException
{
    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly int $status = 422,
        public readonly array $extra = [],
    ) {
        parent::__construct($message);
    }

    public static function make(string $code, int $status = 422, array $replace = [], array $extra = []): self
    {
        return new self($code, __("delivery.errors.{$code}", $replace), $status, $extra);
    }

    public function render(): JsonResponse
    {
        return response()->json(['code' => $this->errorCode, 'message' => $this->getMessage(), ...$this->extra], $this->status);
    }
}
