import 'dart:math';

/// Client-side Haversine distance — used when both coordinates are already
/// on hand (e.g. a cached seller list) and a round trip to the API's
/// server-computed `distance_km` isn't worth it.
double haversineKm(double lat1, double lng1, double lat2, double lng2) {
  const earthRadiusKm = 6371.0;
  final dLat = _degToRad(lat2 - lat1);
  final dLng = _degToRad(lng2 - lng1);

  final a =
      sin(dLat / 2) * sin(dLat / 2) +
      cos(_degToRad(lat1)) * cos(_degToRad(lat2)) * sin(dLng / 2) * sin(dLng / 2);

  return earthRadiusKm * 2 * atan2(sqrt(a), sqrt(1 - a));
}

double _degToRad(double deg) => deg * pi / 180;
