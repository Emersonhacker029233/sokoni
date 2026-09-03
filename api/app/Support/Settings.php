<?php

namespace App\Support;

use App\Models\Setting;

/**
 * Typed reads for the handful of platform values the admin Settings page
 * (CLAUDE.md admin rebuild, Section 6) can override — each getter checks
 * the `settings` table first, falling back to `config('sokoni.*')` when no
 * override has ever been saved. One place values are actually *used*
 * throughout the app (ProductController's default search radius,
 * ProductMediaController's per-product media cap, StoreOfferRequest's
 * duration ceiling, ReportObserver's auto-hide threshold) — a Settings
 * page that didn't change real behaviour would be exactly the kind of
 * prototype-feeling surface this whole rebuild is meant to replace.
 */
class Settings
{
    public static function searchRadiusKm(): float
    {
        return (float) static::get('search_radius_km');
    }

    public static function maxMediaPerProduct(): int
    {
        return (int) static::get('max_media_per_product');
    }

    public static function offerMaxDurationDays(): int
    {
        return (int) static::get('offer_max_duration_days');
    }

    public static function reportAutoHideThreshold(): int
    {
        return (int) static::get('report_auto_hide_threshold');
    }

    /** C6: hard ceiling on a single admin bulk-SMS blast's recipient count. */
    public static function maxSmsBlastSize(): int
    {
        return (int) static::get('max_sms_blast_size');
    }

    public static function get(string $key): mixed
    {
        $override = Setting::query()->find($key);

        return $override?->value ?? config("sokoni.{$key}");
    }

    public static function set(string $key, mixed $value): void
    {
        Setting::query()->updateOrCreate(['key' => $key], ['value' => (string) $value]);
    }

    /** Every overridable key, each paired with its effective current value — powers the Settings page form. */
    public static function all(): array
    {
        return [
            'search_radius_km' => static::searchRadiusKm(),
            'max_media_per_product' => static::maxMediaPerProduct(),
            'offer_max_duration_days' => static::offerMaxDurationDays(),
            'report_auto_hide_threshold' => static::reportAutoHideThreshold(),
            'max_sms_blast_size' => static::maxSmsBlastSize(),
        ];
    }
}
