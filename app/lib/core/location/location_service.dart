import 'package:geolocator/geolocator.dart';

import '../storage/app_database.dart';

/// Outcome of a location request — an explicit sealed result rather than a
/// nullable position, so callers must handle "denied" and "service off"
/// distinctly from "here's a position" (CLAUDE.md feature 1: never block
/// the app on location — fall back to a region/district picker instead).
sealed class LocationResult {
  const LocationResult();
}

final class LocationAvailable extends LocationResult {
  const LocationAvailable(this.lat, this.lng);
  final double lat;
  final double lng;
}

/// Permission was denied (once, or "don't ask again" / permanently).
final class LocationDenied extends LocationResult {
  const LocationDenied({required this.permanently});
  final bool permanently;
}

/// Device location services are off entirely (not a permission issue).
final class LocationServiceDisabled extends LocationResult {
  const LocationServiceDisabled();
}

class LocationService {
  LocationService({required AppDatabase cache}) : _cache = cache;

  final AppDatabase _cache;

  static const _lastLatKey = 'last_known_lat';
  static const _lastLngKey = 'last_known_lng';

  /// Requests permission if needed and returns the current position, or a
  /// reason it couldn't. On success, also updates the cached last-known
  /// position so [cachedPosition] has something to show next launch even
  /// before a fresh fix arrives.
  Future<LocationResult> current() async {
    if (!await Geolocator.isLocationServiceEnabled()) {
      return const LocationServiceDisabled();
    }

    var permission = await Geolocator.checkPermission();
    if (permission == LocationPermission.denied) {
      permission = await Geolocator.requestPermission();
    }

    if (permission == LocationPermission.denied) {
      return const LocationDenied(permanently: false);
    }
    if (permission == LocationPermission.deniedForever) {
      return const LocationDenied(permanently: true);
    }

    final position = await Geolocator.getCurrentPosition(
      locationSettings: const LocationSettings(accuracy: LocationAccuracy.medium),
    );

    await _cache.setKeyValue(_lastLatKey, '${position.latitude}');
    await _cache.setKeyValue(_lastLngKey, '${position.longitude}');

    return LocationAvailable(position.latitude, position.longitude);
  }

  /// Last cached position, if any — shown immediately while [current]
  /// resolves, so the feed doesn't sit empty waiting on a GPS fix.
  Future<(double, double)?> cachedPosition() async {
    final lat = await _cache.getKeyValue(_lastLatKey);
    final lng = await _cache.getKeyValue(_lastLngKey);
    if (lat == null || lng == null) return null;
    return (double.parse(lat), double.parse(lng));
  }
}
