<?php

namespace App\Services\Media;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

/**
 * Generates the three image sizes CLAUDE.md's media feature calls for
 * (thumb 300, card 800, full 1600) from a single uploaded image, storing
 * each on the `public` disk and returning their disk paths (relative to
 * the disk root — the same shape already used by `path`/`thumb_path` on
 * seeded `product_media` rows).
 */
class ImageVariants
{
    private const SIZES = [
        'thumb' => 300,
        'card' => 800,
        'full' => 1600,
    ];

    /** @return array{thumb: string, card: string, full: string} */
    public static function generate(UploadedFile $file, string $directory): array
    {
        $manager = new ImageManager(new Driver());
        $source = $manager->decodePath($file->getRealPath());

        Storage::disk('public')->makeDirectory($directory);

        $paths = [];
        foreach (self::SIZES as $variant => $maxDimension) {
            $resized = (clone $source)->scaleDown(width: $maxDimension, height: $maxDimension);

            $path = "{$directory}/{$variant}_".uniqid('', true).'.jpg';
            $resized->save(Storage::disk('public')->path($path), quality: 82);
            $paths[$variant] = $path;
        }

        return $paths;
    }
}
