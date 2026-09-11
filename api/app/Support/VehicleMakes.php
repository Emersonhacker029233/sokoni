<?php

namespace App\Support;

/**
 * Car makes/models for the Cars category's Make/Model attributes (C3,
 * tester feedback) — the canonical source both the posting form's
 * dependent dropdowns and the category-page filter read from. Same
 * "single flat source of truth" pattern as TanzaniaRegions: reference
 * data with no admin-editable state of its own (unlike Category, which
 * needs is_active/sort_order and so lives in the database), so a plain
 * PHP array is the proportionate choice, not a new table.
 *
 * Weighted toward what's actually common on Tanzanian roads — heavily
 * Japanese/re-imported used cars (Toyota above all), plus the other
 * makes the client named explicitly.
 */
class VehicleMakes
{
    public const ALL = [
        'Toyota' => [
            'Corolla', 'Corolla Fielder', 'Premio', 'Allion', 'Vitz', 'Wish',
            'Noah', 'Voxy', 'Hiace', 'Hilux', 'Land Cruiser', 'Land Cruiser Prado',
            'RAV4', 'Harrier', 'Mark X', 'Passo', 'Probox', 'Succeed',
        ],
        'Nissan' => [
            'Note', 'Tiida', 'X-Trail', 'Navara', 'Patrol', 'Wingroad',
            'Advan', 'Caravan', 'Serena', 'Juke', 'Sunny',
        ],
        'Suzuki' => [
            'Alto', 'Swift', 'Vitara', 'Escudo', 'Every', 'Wagon R', 'Jimny',
        ],
        'Mitsubishi' => [
            'Pajero', 'Pajero Sport', 'L200', 'Outlander', 'Lancer', 'Canter', 'RVR',
        ],
        'Honda' => [
            'Fit', 'Vezel', 'CR-V', 'Civic', 'Accord', 'Freed', 'Stream', 'Insight',
        ],
        'Land Rover' => [
            'Range Rover', 'Range Rover Sport', 'Range Rover Evoque', 'Discovery', 'Defender',
        ],
        'Isuzu' => [
            'D-Max', 'NPR', 'FRR', 'MU-X',
        ],
        'Mazda' => [
            'Demio', 'Axela', 'CX-5', 'BT-50', 'Premacy',
        ],
        'Subaru' => [
            'Forester', 'Impreza', 'Outback', 'Legacy', 'XV',
        ],
        'Volkswagen' => [
            'Golf', 'Passat', 'Tiguan', 'Polo',
        ],
    ];

    /** @return array<int, string> */
    public static function makes(): array
    {
        return array_keys(self::ALL);
    }

    /** @return array<int, string> */
    public static function modelsFor(?string $make): array
    {
        return self::ALL[$make] ?? [];
    }

    public static function isValidMake(string $make): bool
    {
        return array_key_exists($make, self::ALL);
    }

    public static function isValidModel(string $make, string $model): bool
    {
        return in_array($model, self::modelsFor($make), true);
    }

    /**
     * C4 (tester feedback): the third Cars dropdown, "Years 1990 to the
     * current year" per the task's own wording — a flat range, not one
     * filtered by make/model. There's no reliable per-model year-range
     * data behind the fixed list above, and inventing one would be
     * guessing at data rather than using it, the exact thing this project
     * avoids. Newest first, since a used-car lister is far more likely to
     * be listing something recent than something from 1990.
     *
     * @return array<int, int>
     */
    public static function years(): array
    {
        return range((int) date('Y'), 1990);
    }

    public static function isValidYear(int $year): bool
    {
        return $year >= 1990 && $year <= (int) date('Y');
    }
}
