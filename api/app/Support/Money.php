<?php

namespace App\Support;

/**
 * "TSh 45,000 — separators, no decimals" (CLAUDE.md) — the same
 * formatting rule the Flutter app's `intl` helper applies, mirrored here
 * since the API has only ever returned raw integers and every previous
 * consumer (the app) formatted client-side. Server-rendered HTML has no
 * client-side formatting step, so this is the one place it needs to live
 * for the website.
 */
class Money
{
    public static function format(int|float $amountTzs): string
    {
        return 'TSh '.number_format((float) $amountTzs, 0, '.', ',');
    }
}
