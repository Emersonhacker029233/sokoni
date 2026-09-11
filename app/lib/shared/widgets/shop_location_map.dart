import 'package:flutter/material.dart';
import 'package:flutter_map/flutter_map.dart';
import 'package:latlong2/latlong.dart';
import 'package:url_launcher/url_launcher.dart';
import 'package:visibility_detector/visibility_detector.dart';

import '../../core/l10n/gen/app_localizations.dart';
import '../../core/theme/colors.dart';
import '../../core/theme/dimens.dart';

/// A shop's location, shown once, reused wherever the app needs to *display*
/// (not pick) a single point — currently just the shop profile screen.
///
/// Tester feedback: the shop profile's map "behaves like a picture" — it
/// really was one. The previous `_ShopLocationMap` wrapped a real
/// `FlutterMap` in `IgnorePointer` with `InteractiveFlag.none` and had no
/// tap handler anywhere, so nothing about "tap through to a full map" (its
/// own docblock's claim) was ever wired up. `discovery_map_view.dart`
/// (multi-pin browse map) and `step2_location.dart` (drag-to-pick during
/// onboarding) are checked and already fully interactive — this widget
/// doesn't replace either, since both are a genuinely different
/// interaction model (many pins / an editable picker) from "show one
/// fixed point read-only," which is what this is for.
///
/// OpenStreetMap tiles via `flutter_map`, same as every other map in this
/// app — no Google Maps key exists or is coming (billing can't be
/// enabled on this account), so there is no other option, not a
/// preference between two.
class ShopLocationMap extends StatefulWidget {
  const ShopLocationMap({required this.shopName, required this.address, this.lat, this.lng, super.key});

  final String shopName;
  final String? address;
  final double? lat;
  final double? lng;

  @override
  State<ShopLocationMap> createState() => _ShopLocationMapState();
}

class _ShopLocationMapState extends State<ShopLocationMap> {
  static const _maxZoom = 18.0;

  bool _tilesRequested = false;

  bool get _hasLocation => widget.lat != null && widget.lng != null;

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);

    // Null coordinates: the address line alone, never an empty map frame —
    // a `160`px box with nothing real in it reads as broken, same as a
    // dead static image did.
    if (!_hasLocation) {
      if (widget.address == null || widget.address!.isEmpty) return const SizedBox.shrink();
      return Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(Icons.location_on_outlined, size: 16, color: Theme.of(context).hintColor),
          const SizedBox(width: SokoniDimens.space8),
          Expanded(child: Text(widget.address!, style: Theme.of(context).textTheme.bodyMedium)),
        ],
      );
    }

    final point = LatLng(widget.lat!, widget.lng!);

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(l10n.shopLocationTitle, style: Theme.of(context).textTheme.titleSmall),
        const SizedBox(height: SokoniDimens.space8),
        ClipRRect(
          borderRadius: BorderRadius.circular(SokoniDimens.radiusCard),
          child: SizedBox(
            height: 180,
            child: VisibilityDetector(
              // 3G market (CLAUDE.md) — tiles are real network requests;
              // this map sits well down the shop profile's scroll, so it
              // must not cost anything until it's actually on screen.
              key: Key('shop-location-map-${widget.lat}-${widget.lng}'),
              onVisibilityChanged: (info) {
                if (!_tilesRequested && info.visibleFraction > 0.1 && mounted) {
                  setState(() => _tilesRequested = true);
                }
              },
              child: _tilesRequested
                  ? Stack(
                      children: [
                        FlutterMap(
                          options: MapOptions(
                            initialCenter: point,
                            // Street-level, not "the whole country" — the
                            // task's own phrasing for the right default.
                            initialZoom: 15,
                            maxZoom: _maxZoom,
                          ),
                          children: [
                            TileLayer(
                              urlTemplate: 'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
                              userAgentPackageName: 'tz.co.sokoni.sokoni',
                              maxNativeZoom: _maxZoom.toInt(),
                            ),
                            MarkerLayer(
                              markers: [
                                Marker(
                                  point: point,
                                  width: 44,
                                  height: 44,
                                  child: GestureDetector(
                                    onTap: () => _showShopInfoSheet(context, l10n),
                                    child: const Icon(
                                      Icons.location_on,
                                      color: SokoniColors.sokoniYellow,
                                      size: 40,
                                      shadows: [Shadow(color: Colors.black45, blurRadius: 4)],
                                    ),
                                  ),
                                ),
                              ],
                            ),
                          ],
                        ),
                        Positioned(
                          right: SokoniDimens.space8,
                          bottom: SokoniDimens.space8,
                          child: _DirectionsButton(lat: widget.lat!, lng: widget.lng!, label: l10n.shopLocationDirections),
                        ),
                      ],
                    )
                  : Container(color: Theme.of(context).colorScheme.surfaceContainerHighest),
            ),
          ),
        ),
      ],
    );
  }

  void _showShopInfoSheet(BuildContext context, AppLocalizations l10n) {
    showModalBottomSheet<void>(
      context: context,
      useSafeArea: true,
      builder: (sheetContext) => Padding(
        padding: const EdgeInsets.all(SokoniDimens.space20),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(widget.shopName, style: Theme.of(sheetContext).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w700)),
            if (widget.address != null && widget.address!.isNotEmpty) ...[
              const SizedBox(height: SokoniDimens.space4),
              Text(widget.address!, style: Theme.of(sheetContext).textTheme.bodyMedium),
            ],
            const SizedBox(height: SokoniDimens.space16),
            SizedBox(
              width: double.infinity,
              child: _DirectionsButton(lat: widget.lat!, lng: widget.lng!, label: l10n.shopLocationDirections, expanded: true),
            ),
          ],
        ),
      ),
    );
  }
}

class _DirectionsButton extends StatelessWidget {
  const _DirectionsButton({required this.lat, required this.lng, required this.label, this.expanded = false});

  final double lat;
  final double lng;
  final String label;
  final bool expanded;

  @override
  Widget build(BuildContext context) {
    if (expanded) {
      return FilledButton.icon(
        onPressed: () => _openDirections(lat, lng),
        icon: const Icon(Icons.directions_rounded),
        label: Text(label),
      );
    }

    return FilledButton.tonalIcon(
      style: FilledButton.styleFrom(backgroundColor: Colors.white, foregroundColor: SokoniColors.sokoniBlack),
      onPressed: () => _openDirections(lat, lng),
      icon: const Icon(Icons.directions_rounded, size: 18),
      label: Text(label),
    );
  }

  /// `geo:` is the standard Android intent scheme for "open in whichever
  /// map app the user already has" — not guaranteed to resolve on every
  /// device/OS, so a plain web URL (works in any browser, no API key,
  /// no billing) is the fallback, not a second attempt at the same thing.
  Future<void> _openDirections(double lat, double lng) async {
    final geoUri = Uri.parse('geo:$lat,$lng?q=$lat,$lng');
    if (await canLaunchUrl(geoUri)) {
      await launchUrl(geoUri);
      return;
    }

    final webUri = Uri.parse('https://www.google.com/maps/dir/?api=1&destination=$lat,$lng');
    await launchUrl(webUri, mode: LaunchMode.externalApplication);
  }
}
