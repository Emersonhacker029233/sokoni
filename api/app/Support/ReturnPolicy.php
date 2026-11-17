<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * A shop's return policy (docs/delivery/RETURNS.md): none, 24h, 3d or 7d,
 * counted from the moment Bolt delivers. `none` is the default, so every
 * existing shop is unchanged until its owner chooses otherwise.
 */
final class ReturnPolicy
{
    /** code => hours */
    public const OPTIONS = ['none' => 0, '24h' => 24, '3d' => 72, '7d' => 168];

    public static function hours(?string $code): int
    {
        return self::OPTIONS[$code ?? 'none'] ?? 0;
    }

    public static function accepts(?string $code): bool
    {
        return self::hours($code) > 0;
    }

    /** Until when a return may be requested, or null when the policy is none or nothing was delivered. */
    public static function deadline(?string $code, ?Carbon $deliveredAt): ?Carbon
    {
        return self::accepts($code) && $deliveredAt !== null ? $deliveredAt->copy()->addHours(self::hours($code)) : null;
    }

    /** The shape every API response uses: shop page, product page, checkout quote and order screen. */
    public static function toArray(?string $code, ?string $notes = null, ?string $locale = null): array
    {
        $code = array_key_exists((string) $code, self::OPTIONS) ? (string) $code : 'none';

        return [
            'code' => $code,
            'hours' => self::OPTIONS[$code],
            'label' => __("returns.policy.{$code}", [], $locale),
            'notes' => $notes !== null && trim($notes) !== '' ? trim($notes) : null,
        ];
    }
}
