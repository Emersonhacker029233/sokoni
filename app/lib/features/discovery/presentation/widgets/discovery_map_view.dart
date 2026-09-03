import 'package:flutter/material.dart';
import 'package:flutter_map/flutter_map.dart';
import 'package:latlong2/latlong.dart';

import '../../../../core/l10n/gen/app_localizations.dart';
import '../../../../core/theme/colors.dart';
import '../../../../data/models/product.dart';
import '../../../../data/models/seller_summary.dart';
import '../../../../shared/widgets/empty_state.dart';

/// Map view for the discovery feed — one marker per seller (deduplicated
/// across that seller's products in the current results) at that seller's
/// real shop location, tapping a marker opens a seller quick-view sheet
/// (CLAUDE.md feature 1).
///
/// OpenStreetMap via `flutter_map`, not Google Maps — no Maps API key was
/// ever configured on this project (billing was never enabled, a
/// documented constraint), which meant this view had *always* shown the
/// "map unavailable" empty state instead of an actual map (tester feedback
/// B3). OSM's public tile server needs no key at all, matching what the
/// website already does for its own shop-location embeds. Markers aren't
/// pixel-clustered at low zoom yet — that would need a dedicated
/// clustering package on top of this one; see DECISIONS.md.
class DiscoveryMapView extends StatelessWidget {
  const DiscoveryMapView({
    required this.products,
    required this.center,
    required this.onSellerTap,
    this.onSwitchToList,
    super.key,
  });

  final List<Product> products;
  final (double lat, double lng) center;
  final ValueChanged<SellerSummary> onSellerTap;
  final VoidCallback? onSwitchToList;

  @override
  Widget build(BuildContext context) {
    final sellers = <int, SellerSummary>{};
    for (final product in products) {
      final seller = product.seller;
      if (seller != null && seller.lat != null && seller.lng != null) {
        sellers[seller.id] = seller;
      }
    }

    if (sellers.isEmpty) {
      final l10n = AppLocalizations.of(context);
      return SokoniEmptyState(
        icon: Icons.map_outlined,
        title: l10n.feedEmptyTitle,
        message: l10n.feedEmptyBody,
      );
    }

    return FlutterMap(
      options: MapOptions(
        initialCenter: LatLng(center.$1, center.$2),
        initialZoom: 12,
        // A hard ceiling, not just a sensible default — OSM's usage policy
        // and this being a 3G-target app both favour never fetching the
        // most detailed (largest, most numerous) tile set.
        maxZoom: 17,
      ),
      children: [
        TileLayer(
          urlTemplate: 'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
          // Required by OSM's tile usage policy to identify the app —
          // flutter_map only fetches tiles for the visible viewport at the
          // current zoom (lazy by construction, nothing to opt into).
          userAgentPackageName: 'tz.co.sokoni.sokoni',
          maxNativeZoom: 17,
        ),
        MarkerLayer(
          markers: [
            for (final seller in sellers.values)
              Marker(
                point: LatLng(seller.lat!, seller.lng!),
                width: 40,
                height: 40,
                child: GestureDetector(
                  onTap: () => onSellerTap(seller),
                  child: const Icon(Icons.location_on, color: SokoniColors.sokoniYellow, size: 40, shadows: [
                    Shadow(color: Colors.black45, blurRadius: 4),
                  ]),
                ),
              ),
          ],
        ),
      ],
    );
  }
}
