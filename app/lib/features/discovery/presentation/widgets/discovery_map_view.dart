import 'package:flutter/material.dart';
import 'package:google_maps_flutter/google_maps_flutter.dart';

import '../../../../core/l10n/gen/app_localizations.dart';
import '../../../../core/theme/colors.dart';
import '../../../../core/theme/dimens.dart';
import '../../../../data/models/product.dart';
import '../../../../data/models/seller_summary.dart';

/// Map view for the discovery feed — one marker per seller (deduplicated
/// across that seller's products in the current results) at that seller's
/// real shop location, tapping a marker opens a seller quick-view sheet
/// (CLAUDE.md feature 1). Markers aren't pixel-clustered at low zoom yet —
/// that needs a dedicated clustering package on top of google_maps_flutter;
/// see DECISIONS.md.
class DiscoveryMapView extends StatelessWidget {
  const DiscoveryMapView({
    required this.products,
    required this.center,
    required this.onSellerTap,
    super.key,
  });

  final List<Product> products;
  final (double lat, double lng) center;
  final ValueChanged<SellerSummary> onSellerTap;

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
      return const _MapUnavailable();
    }

    final markers = <Marker>{
      for (final seller in sellers.values)
        Marker(
          markerId: MarkerId('seller-${seller.id}'),
          position: LatLng(seller.lat!, seller.lng!),
          infoWindow: InfoWindow(title: seller.shopName),
          onTap: () => onSellerTap(seller),
        ),
    };

    return GoogleMap(
      initialCameraPosition: CameraPosition(target: LatLng(center.$1, center.$2), zoom: 12),
      markers: markers,
      myLocationButtonEnabled: false,
    );
  }
}

class _MapUnavailable extends StatelessWidget {
  const _MapUnavailable();

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    return Container(
      color: SokoniColors.surfaceAlt,
      alignment: Alignment.center,
      padding: const EdgeInsets.all(SokoniDimens.space24),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          const Icon(Icons.map_outlined, size: 40),
          const SizedBox(height: SokoniDimens.space12),
          Text(l10n.feedEmptyTitle, textAlign: TextAlign.center),
        ],
      ),
    );
  }
}
