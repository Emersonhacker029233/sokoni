<?php

namespace App\Filament\Support;

use App\Services\Delivery\DriverLocationService;
use Closure;
use Filament\Infolists\Components\TextEntry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * "Where is the driver now?" for admins, on a Bolt delivery or a return's
 * ride back while a driver is on it: the coordinates, how old they are, and
 * a link that opens them on OpenStreetMap. It uses the same per-ride cached
 * lookup as the buyer's map (DriverLocationService), so opening the page
 * costs at most one Bolt location call per ride per 5 seconds. Only view
 * pages use it; list pages link to Bolt's own tracking page instead, which
 * costs no API call. Reload the page for a newer position.
 */
final class LivePosition
{
    /** @param Closure(Model): ?string $liveRideId the Bolt ride id while a driver is on it, else null */
    public static function entry(string $name, Closure $liveRideId): TextEntry
    {
        return TextEntry::make($name)
            ->label('Driver position now')
            ->state(fn (Model $record) => self::describe($liveRideId($record)))
            ->url(fn (Model $record) => self::url($liveRideId($record)), shouldOpenInNewTab: true)
            ->placeholder('Only while a Bolt driver is on the ride')
            ->helperText('Reload the page for the latest position.');
    }

    public static function describe(?string $rideId): ?string
    {
        $answer = self::lookup($rideId);
        if ($answer === null) {
            return null;
        }
        $location = $answer['location'];
        if ($location === null) {
            return match ($answer['reason']) {
                'driver_not_located' => 'Bolt cannot locate the driver right now',
                'rate_limited' => 'Bolt is rate limiting: reload in a few seconds',
                'ride_ended' => 'The ride has ended at Bolt',
                default => 'Not available from Bolt right now',
            };
        }

        $age = isset($location['recorded_at']) ? Carbon::parse($location['recorded_at'])->diffForHumans() : null;

        return sprintf('%.5f, %.5f', $location['lat'], $location['lng'])
            .($age !== null ? " · {$age}" : '')
            .($answer['stale'] ? ' · last known' : '')
            .' · open map';
    }

    public static function url(?string $rideId): ?string
    {
        $location = self::lookup($rideId)['location'] ?? null;

        return $location === null ? null
            : sprintf('https://www.openstreetmap.org/?mlat=%1$.6f&mlon=%2$.6f#map=17/%1$.6f/%2$.6f', $location['lat'], $location['lng']);
    }

    /** @return array{location: ?array, stale: bool, reason: ?string, checked_at: ?string}|null */
    private static function lookup(?string $rideId): ?array
    {
        if ($rideId === null || $rideId === '') {
            return null;
        }
        try {
            return app(DriverLocationService::class)->forRide($rideId);
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }
}
