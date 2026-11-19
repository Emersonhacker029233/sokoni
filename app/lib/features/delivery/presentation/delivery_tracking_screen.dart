import 'dart:math' as math;

import 'package:flutter/material.dart';
import 'package:flutter_map/flutter_map.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';
import 'package:latlong2/latlong.dart';

import '../../../core/l10n/gen/app_localizations.dart';
import '../../../core/theme/colors.dart';
import '../../../core/theme/dimens.dart';
import '../../../data/models/delivery.dart';
import '../../../shared/widgets/error_state.dart';
import '../providers/delivery_providers.dart';
import 'widgets/delivery_widgets.dart';

/// The buyer's live Bolt tracking (docs/delivery/BOLT.md, "Live tracking").
///
/// A map with the shop, the drop-off point and the Bolt driver moving
/// between them, and a draggable panel with the ETA, the driver (call,
/// "Track on Bolt") and the delivery timeline. The driver's position comes
/// from `GET /delivery/orders/{order}/location`, polled every ~5 s only
/// while this screen is open and the app is in the foreground, and not at
/// all once the ride is over.
///
/// The map is OpenStreetMap through flutter_map, the same package and tiles
/// as the checkout pin: no map key.
class DeliveryTrackingScreen extends ConsumerStatefulWidget {
  const DeliveryTrackingScreen({required this.orderId, this.showTiles = true, super.key});

  final int orderId;

  /// OpenStreetMap tiles. Widget tests switch them off (no network there).
  final bool showTiles;

  @override
  ConsumerState<DeliveryTrackingScreen> createState() => _DeliveryTrackingScreenState();
}

class _DeliveryTrackingScreenState extends ConsumerState<DeliveryTrackingScreen> {
  late final AppLifecycleListener _lifecycle;

  @override
  void initState() {
    super.initState();
    // No polling while the phone is locked or another app is in front.
    _lifecycle = AppLifecycleListener(
      onHide: () => ref.read(driverLocationProvider(widget.orderId).notifier).setPaused(true),
      onShow: () => ref.read(driverLocationProvider(widget.orderId).notifier).setPaused(false),
    );
  }

  @override
  void dispose() {
    _lifecycle.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    final mapAsync = ref.watch(driverLocationProvider(widget.orderId));
    final tracking = ref.watch(deliveryTrackingProvider(widget.orderId)).value;

    return Scaffold(
      appBar: AppBar(title: Text(l10n.deliveryLiveTitle)),
      body: mapAsync.when(
        skipLoadingOnRefresh: true,
        loading: () => const _MapPlaceholder(),
        error: (_, _) => SokoniErrorState(
          message: l10n.deliveryLiveLoadFailed,
          onRetry: () => ref.invalidate(driverLocationProvider(widget.orderId)),
        ),
        data: (map) => Stack(
          children: [
            Positioned.fill(
              child: _LiveMap(
                pickup: map.location.pickup ?? tracking?.pickup,
                dropoff: map.location.dropoff ?? tracking?.dropoff,
                driver: map.location.position,
                stale: map.location.stale || map.reconnecting,
                showTiles: widget.showTiles,
              ),
            ),
            DraggableScrollableSheet(
              initialChildSize: 0.42,
              minChildSize: 0.2,
              maxChildSize: 0.9,
              snap: true,
              builder: (context, scroll) => _Panel(map: map, tracking: tracking, scroll: scroll),
            ),
          ],
        ),
      ),
    );
  }
}

class _MapPlaceholder extends StatelessWidget {
  const _MapPlaceholder();

  @override
  Widget build(BuildContext context) {
    final dark = Theme.of(context).brightness == Brightness.dark;
    return ColoredBox(
      color: dark ? SokoniColors.darkSurfaceAlt : SokoniColors.surfaceAlt,
      child: const Align(alignment: Alignment.topCenter, child: LinearProgressIndicator()),
    );
  }
}

/// The map: shop, drop-off, a faint line between them, and the driver,
/// who glides to each new position instead of jumping (one controller for
/// the screen, 400 ms, skipped when the system asks for less motion).
class _LiveMap extends StatefulWidget {
  const _LiveMap({required this.pickup, required this.dropoff, required this.driver, required this.stale, required this.showTiles});

  final DeliveryPoint? pickup;
  final DeliveryPoint? dropoff;
  final DriverPosition? driver;
  final bool stale;
  final bool showTiles;

  @override
  State<_LiveMap> createState() => _LiveMapState();
}

class _LiveMapState extends State<_LiveMap> with SingleTickerProviderStateMixin {
  static const _darEsSalaam = LatLng(-6.7924, 39.2083);

  final _map = MapController();
  late final AnimationController _glide = AnimationController(vsync: this, duration: const Duration(milliseconds: 400));
  LatLng? _from;
  LatLng? _to;
  bool _ready = false;

  @override
  void initState() {
    super.initState();
    final driver = widget.driver;
    if (driver != null) _from = _to = LatLng(driver.lat, driver.lng);
  }

  @override
  void didUpdateWidget(_LiveMap old) {
    super.didUpdateWidget(old);
    final next = widget.driver;
    if (next == null) return;
    final target = LatLng(next.lat, next.lng);
    if (_to == target) return;
    final firstFix = _to == null;
    _from = _to == null ? target : _current();
    _to = target;
    if (firstFix) {
      // The driver just appeared: bring everyone into view once.
      WidgetsBinding.instance.addPostFrameCallback((_) => _fit());
    } else if (MediaQuery.disableAnimationsOf(context)) {
      _glide.value = 1;
    } else {
      _glide.forward(from: 0);
    }
  }

  @override
  void dispose() {
    _glide.dispose();
    _map.dispose();
    super.dispose();
  }

  LatLng? _point(DeliveryPoint? p) => p == null ? null : LatLng(p.lat, p.lng);

  LatLng _current() {
    final from = _from!;
    final to = _to!;
    final t = Curves.easeOutCubic.transform(_glide.value);
    return LatLng(from.latitude + (to.latitude - from.latitude) * t, from.longitude + (to.longitude - from.longitude) * t);
  }

  /// Fits the shop, the drop-off and the driver above the panel.
  void _fit() {
    if (!_ready || !mounted) return;
    final points = [_point(widget.pickup), _point(widget.dropoff), _to].whereType<LatLng>().toList();
    if (points.isEmpty) return;
    if (points.length == 1) {
      _map.move(points.first, 15);
      return;
    }
    final height = context.size?.height ?? 600;
    _map.fitCamera(
      CameraFit.coordinates(
        coordinates: points,
        maxZoom: 16,
        padding: EdgeInsets.fromLTRB(48, 72, 48, height * 0.42 + 32),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    final pickup = _point(widget.pickup);
    final dropoff = _point(widget.dropoff);

    return Stack(
      children: [
        FlutterMap(
          key: const Key('live-map'),
          mapController: _map,
          options: MapOptions(
            initialCenter: pickup ?? dropoff ?? _darEsSalaam,
            initialZoom: 13,
            maxZoom: 19,
            onMapReady: () {
              _ready = true;
              _fit();
            },
          ),
          children: [
            if (widget.showTiles)
              TileLayer(
                urlTemplate: 'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
                userAgentPackageName: 'tz.co.sokoni.sokoni',
                maxNativeZoom: 19,
              ),
            if (pickup != null && dropoff != null)
              PolylineLayer(
                polylines: [Polyline(points: [pickup, dropoff], strokeWidth: 3, color: SokoniColors.sokoniBlack.withValues(alpha: 0.25))],
              ),
            MarkerLayer(
              markers: [
                if (pickup != null)
                  Marker(
                    point: pickup,
                    width: 40,
                    height: 40,
                    child: _PointMarker(icon: Icons.storefront_rounded, label: l10n.deliveryLiveShop, markerKey: const Key('live-shop-marker')),
                  ),
                if (dropoff != null)
                  Marker(
                    point: dropoff,
                    width: 44,
                    height: 44,
                    alignment: Alignment.topCenter,
                    child: Semantics(
                      key: const Key('live-dropoff-marker'),
                      label: l10n.deliveryLiveYou,
                      child: const Icon(Icons.location_on_rounded, size: 44, color: SokoniColors.sokoniBlack),
                    ),
                  ),
              ],
            ),
            if (_to != null)
              AnimatedBuilder(
                animation: _glide,
                builder: (context, _) => MarkerLayer(
                  markers: [
                    Marker(
                      point: _current(),
                      width: 44,
                      height: 44,
                      child: _DriverMarker(bearing: widget.driver?.bearing, stale: widget.stale, label: l10n.deliveryLiveDriver),
                    ),
                  ],
                ),
              ),
            if (widget.showTiles) const SimpleAttributionWidget(source: Text('OpenStreetMap')),
          ],
        ),
        Positioned(
          top: SokoniDimens.space12,
          right: SokoniDimens.space12,
          child: IconButton.filledTonal(
            key: const Key('live-recenter'),
            tooltip: l10n.deliveryLiveRecenter,
            onPressed: _fit,
            icon: const Icon(Icons.center_focus_strong_rounded),
          ),
        ),
      ],
    );
  }
}

class _PointMarker extends StatelessWidget {
  const _PointMarker({required this.icon, required this.label, required this.markerKey});

  final IconData icon;
  final String label;
  final Key markerKey;

  @override
  Widget build(BuildContext context) {
    return Semantics(
      key: markerKey,
      label: label,
      child: Container(
        decoration: BoxDecoration(
          color: SokoniColors.sokoniBlack,
          shape: BoxShape.circle,
          border: Border.all(color: SokoniColors.surface, width: 2),
        ),
        child: Icon(icon, size: 20, color: SokoniColors.surface),
      ),
    );
  }
}

/// Yellow when fresh, grey when the position is the last known one. Points
/// the way Bolt says the car is heading, when it says.
class _DriverMarker extends StatelessWidget {
  const _DriverMarker({required this.bearing, required this.stale, required this.label});

  final double? bearing;
  final bool stale;
  final String label;

  @override
  Widget build(BuildContext context) {
    return Semantics(
      key: const Key('live-driver-marker'),
      label: label,
      child: Container(
        decoration: BoxDecoration(
          color: stale ? SokoniColors.outline : SokoniColors.sokoniYellow,
          shape: BoxShape.circle,
          border: Border.all(color: SokoniColors.sokoniBlack, width: 2),
          boxShadow: [
            BoxShadow(
              color: SokoniColors.sokoniBlack.withValues(alpha: SokoniDimens.ambientShadowOpacity * 4),
              blurRadius: SokoniDimens.ambientBlurRadius / 2,
            ),
          ],
        ),
        child: bearing == null
            ? const Icon(Icons.directions_car_rounded, size: 22, color: SokoniColors.onYellow)
            : Transform.rotate(
                angle: bearing! * math.pi / 180,
                child: const Icon(Icons.navigation_rounded, size: 22, color: SokoniColors.onYellow),
              ),
      ),
    );
  }
}

/// The draggable panel under the map.
class _Panel extends StatelessWidget {
  const _Panel({required this.map, required this.tracking, required this.scroll});

  final LiveMapState map;
  final DeliveryTracking? tracking;
  final ScrollController scroll;

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    final theme = Theme.of(context);
    final live = map.location;
    final sub = liveSubline(l10n, map);
    final driver = tracking?.driver;

    return DecoratedBox(
      decoration: BoxDecoration(
        color: theme.colorScheme.surface,
        borderRadius: const BorderRadius.vertical(top: Radius.circular(SokoniDimens.radiusSheet)),
        boxShadow: [
          BoxShadow(
            color: SokoniColors.sokoniBlack.withValues(alpha: SokoniDimens.ambientShadowOpacity),
            blurRadius: SokoniDimens.ambientBlurRadius,
          ),
        ],
      ),
      child: SingleChildScrollView(
        controller: scroll,
        padding: const EdgeInsets.fromLTRB(SokoniDimens.space16, SokoniDimens.space8, SokoniDimens.space16, SokoniDimens.space24),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
          Center(
            child: Container(
              width: 40,
              height: 4,
              margin: const EdgeInsets.only(bottom: SokoniDimens.space12),
              decoration: BoxDecoration(color: theme.dividerColor, borderRadius: BorderRadius.circular(2)),
            ),
          ),
          Text(
            liveHeadline(l10n, live),
            key: const Key('live-headline'),
            style: theme.textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w700),
          ),
          if (sub != null) ...[
            const SizedBox(height: SokoniDimens.space4),
            Text(
              sub,
              key: const Key('live-subline'),
              style: theme.textTheme.bodySmall?.copyWith(color: map.reconnecting ? SokoniColors.danger : null),
            ),
          ],
          const SizedBox(height: SokoniDimens.space16),
          if (driver != null) BoltDriverCard(driver: driver, trackingUrl: tracking?.trackingUrl),
          if (tracking != null) DeliveryCard(title: l10n.deliveryTimelineTitle, child: DeliveryProgress(tracking: tracking!)),
          if (!live.active)
            OutlinedButton(
              key: const Key('live-back-to-order'),
              onPressed: () => context.pop(),
              child: Text(l10n.deliveryLiveBackToOrder),
            ),
          ],
        ),
      ),
    );
  }
}

/// The panel's main line: the ETA when there is one, else what is happening.
String liveHeadline(AppLocalizations l10n, DriverLiveLocation live) {
  final eta = live.eta;
  return switch (live.status) {
    'pending_driver' => l10n.deliveryLiveFinding,
    'driver_assigned' => eta != null && eta.to == 'shop' ? l10n.deliveryLiveEtaToShop(eta.minutes) : l10n.deliveryStageAssigned,
    'arrived_at_shop' => l10n.deliveryLiveAtShop,
    'on_the_way' => eta != null && eta.to == 'customer' ? l10n.deliveryLiveEtaToYou(eta.minutes) : l10n.deliveryStageOnTheWay,
    'delivered' => l10n.deliveryLiveDelivered,
    _ => l10n.deliveryLiveEnded,
  };
}

/// What the buyer should know about the map itself: reconnecting, still
/// locating the driver, an old position, or an estimated ETA.
String? liveSubline(AppLocalizations l10n, LiveMapState map) {
  final live = map.location;
  if (map.reconnecting) return l10n.deliveryLiveReconnecting;
  if (!live.active) return null;
  final driverOnJob = const {'driver_assigned', 'arrived_at_shop', 'on_the_way'}.contains(live.status);
  if (driverOnJob && live.position == null) return l10n.deliveryLiveLocating;
  if (live.stale) {
    final at = live.position?.recordedAt;
    return at == null ? l10n.deliveryLiveStale : l10n.deliveryLiveLastSeen(DateFormat('HH:mm').format(at));
  }
  if (live.status == 'on_the_way' && (live.eta?.isEstimate ?? false)) return l10n.deliveryLiveEtaEstimateNote;
  return null;
}
