<?php

namespace App\Support;

use Carbon\Carbon;

/**
 * Parses `seller_profiles.opening_hours` (a JSON object keyed by
 * lowercase weekday, each value either null/closed or {open, close} in
 * 24h "HH:MM") into an ordered Monday→Sunday structure for both the shop
 * page's display and its schema.org LocalBusiness `openingHoursSpecification`.
 * The app's Edit Profile screen writes this same shape — see
 * DECISIONS.md for the exact contract both sides agreed on.
 */
class OpeningHours
{
    public const DAYS = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

    /** @return array<string, array{open: string, close: string}|null> */
    public static function parse(?array $raw): array
    {
        $raw ??= [];

        $result = [];
        foreach (self::DAYS as $day) {
            $value = $raw[$day] ?? null;
            $result[$day] = (is_array($value) && isset($value['open'], $value['close']))
                ? ['open' => $value['open'], 'close' => $value['close']]
                : null;
        }

        return $result;
    }

    public static function isOpenNow(?array $raw): bool
    {
        $today = self::DAYS[Carbon::now()->dayOfWeekIso - 1];
        $hours = self::parse($raw)[$today] ?? null;

        if (! $hours) {
            return false;
        }

        $now = Carbon::now()->format('H:i');

        return $now >= $hours['open'] && $now <= $hours['close'];
    }

    /** schema.org OpeningHoursSpecification entries, one per open day. */
    public static function toSchemaOrg(?array $raw): array
    {
        $dayNames = ['monday' => 'Monday', 'tuesday' => 'Tuesday', 'wednesday' => 'Wednesday', 'thursday' => 'Thursday', 'friday' => 'Friday', 'saturday' => 'Saturday', 'sunday' => 'Sunday'];

        return collect(self::parse($raw))
            ->filter()
            ->map(fn (array $hours, string $day) => [
                '@type' => 'OpeningHoursSpecification',
                'dayOfWeek' => 'https://schema.org/'.$dayNames[$day],
                'opens' => $hours['open'],
                'closes' => $hours['close'],
            ])
            ->values()
            ->all();
    }
}
