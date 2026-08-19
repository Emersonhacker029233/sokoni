<?php

namespace App\Support;

/**
 * The 31 official regions of Tanzania (26 mainland + 5 Zanzibar) — the
 * canonical list backing the website's region selector and the
 * region/district free-text fields already stored on `seller_profiles`.
 * Kept as a single flat source of truth rather than hardcoded per-view,
 * since it's referenced by the search filter, seller onboarding context,
 * and (later) the sitemap.
 */
class TanzaniaRegions
{
    public const ALL = [
        'Arusha', 'Dar es Salaam', 'Dodoma', 'Geita', 'Iringa', 'Kagera',
        'Katavi', 'Kigoma', 'Kilimanjaro', 'Lindi', 'Manyara', 'Mara',
        'Mbeya', 'Morogoro', 'Mtwara', 'Mwanza', 'Njombe', 'Pwani',
        'Rukwa', 'Ruvuma', 'Shinyanga', 'Simiyu', 'Singida', 'Songwe',
        'Tabora', 'Tanga',
        'Kaskazini Pemba', 'Kaskazini Unguja', 'Kusini Pemba', 'Kusini Unguja', 'Mjini Magharibi',
    ];

    public const DEFAULT = 'Dar es Salaam';

    public static function slug(string $region): string
    {
        return str($region)->slug()->toString();
    }

    /** @return array<string, string> slug => display name, for a <select> */
    public static function options(): array
    {
        return collect(self::ALL)->mapWithKeys(fn (string $region) => [self::slug($region) => $region])->all();
    }

    public static function fromSlug(string $slug): ?string
    {
        return collect(self::ALL)->first(fn (string $region) => self::slug($region) === $slug);
    }
}
