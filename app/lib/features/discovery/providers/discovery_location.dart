import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_riverpod/legacy.dart';

import '../../../core/location/location_service.dart';
import '../../../core/providers.dart';

class DiscoveryCoords {
  const DiscoveryCoords(this.lat, this.lng);
  final double lat;
  final double lng;
}

/// Result of resolving "where is the user" for the discovery feed. `coords`
/// is null only when GPS failed *and* no cached position *and* no district
/// was picked — the feed still works in that state, just without distance
/// sorting (CLAUDE.md feature 1: never block the app on location).
class DiscoveryLocation {
  const DiscoveryLocation({this.coords, this.needsFallbackPicker = false});

  final DiscoveryCoords? coords;

  /// True when GPS is unavailable/denied and there's no cached position to
  /// fall back to yet — the UI should offer the district picker.
  final bool needsFallbackPicker;
}

/// Dar es Salaam district centroids — same coordinates used to seed demo
/// sellers (see DatabaseSeeder), so picking a district here lines up with
/// where the seeded shops actually are.
const daresSalaamDistricts = <String, (double lat, double lng)>{
  'Kinondoni': (-6.7735, 39.2695),
  'Ilala': (-6.8161, 39.2803),
  'Temeke': (-6.8735, 39.2695),
  'Ubungo': (-6.7735, 39.2200),
  'Kigamboni': (-6.8300, 39.3100),
};

/// Manually-picked district, used only when GPS is unavailable/denied.
final selectedDistrictProvider = StateProvider<String?>((ref) => null);

final discoveryLocationProvider = FutureProvider<DiscoveryLocation>((ref) async {
  final district = ref.watch(selectedDistrictProvider);
  if (district != null && daresSalaamDistricts.containsKey(district)) {
    final (lat, lng) = daresSalaamDistricts[district]!;
    return DiscoveryLocation(coords: DiscoveryCoords(lat, lng));
  }

  final service = ref.watch(locationServiceProvider);
  final result = await service.current();

  switch (result) {
    case LocationAvailable(:final lat, :final lng):
      return DiscoveryLocation(coords: DiscoveryCoords(lat, lng));
    case LocationDenied():
    case LocationServiceDisabled():
      final cached = await service.cachedPosition();
      if (cached != null) {
        final (lat, lng) = cached;
        return DiscoveryLocation(coords: DiscoveryCoords(lat, lng));
      }
      return const DiscoveryLocation(needsFallbackPicker: true);
  }
});
