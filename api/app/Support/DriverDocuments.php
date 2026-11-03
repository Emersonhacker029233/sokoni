<?php

namespace App\Support;

use App\Models\DriverProfile;
use Illuminate\Support\Facades\Storage;

/**
 * The document images a driver uploads (photo, NIDA card, licence, and a
 * vehicle photo if one ever exists). They live on the PRIVATE `local`
 * disk, which only serves a file with a valid signed URL, and the API
 * only ever signs the driver photo. Admins read them through
 * DriverDocumentController, which is inside the panel's auth and checks
 * the admin role itself.
 */
class DriverDocuments
{
    public const KEYS = ['photo', 'national-id', 'licence', 'vehicle'];

    public const LABELS = [
        'photo' => 'Driver photo',
        'national-id' => 'NIDA card',
        'licence' => 'Driving licence',
        'vehicle' => 'Vehicle photo',
    ];

    public static function path(DriverProfile $driver, string $key): ?string
    {
        $path = match ($key) {
            'photo' => $driver->photo,
            'national-id' => $driver->national_id_image,
            'licence' => $driver->licence_image,
            'vehicle' => $driver->activeVehicle?->photo,
            default => null,
        };

        return $path === null || $path === '' ? null : $path;
    }

    /**
     * What the review page shows: one card per document. The vehicle card
     * only appears when a vehicle photo exists (the app doesn't collect one).
     *
     * @return list<array{key: string, label: string, present: bool, is_pdf: bool, url: ?string, download_url: ?string}>
     */
    public static function forAdmin(DriverProfile $driver): array
    {
        $cards = [];
        foreach (self::KEYS as $key) {
            $path = self::path($driver, $key);
            if ($key === 'vehicle' && $path === null) {
                continue;
            }
            $present = $path !== null && Storage::disk('local')->exists($path);
            $url = $present ? route('filament.admin.driver-documents.show', ['driver' => $driver->id, 'document' => $key]) : null;
            $cards[] = [
                'key' => $key,
                'label' => self::LABELS[$key],
                'present' => $present,
                'is_pdf' => $present && strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'pdf',
                'url' => $url,
                'download_url' => $url === null ? null : $url.'?download=1',
            ];
        }

        return $cards;
    }
}
