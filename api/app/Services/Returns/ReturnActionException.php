<?php

namespace App\Services\Returns;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * A return action the caller may not take right now. Rendered like the
 * delivery errors: a stable `code` the app switches on, and a `message`
 * in the user's language (lang/{en,sw}/returns.php) it can show as is.
 */
class ReturnActionException extends RuntimeException
{
    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly int $status = 422,
    ) {
        parent::__construct($message);
    }

    public static function make(string $code, int $status = 422, array $replace = []): self
    {
        return new self($code, __("returns.errors.{$code}", $replace), $status);
    }

    public function render(): JsonResponse
    {
        return response()->json(['code' => $this->errorCode, 'message' => $this->getMessage()], $this->status);
    }
}
