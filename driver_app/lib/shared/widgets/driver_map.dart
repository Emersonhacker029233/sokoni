import 'package:flutter/material.dart';
import 'package:flutter_map/flutter_map.dart';
import 'package:latlong2/latlong.dart';

import '../../core/theme/colors.dart';

/// A pin on [DriverMap].
class MapPin {
  const MapPin({required this.point, required this.icon, this.label, this.highlight = false});

  final LatLng point;
  final IconData icon;
  final String? label;
  final bool highlight;
}

/// OpenStreetMap via flutter_map, the same no-key setup as the customer
/// app. Turn-by-turn navigation is handed to Google Maps by URL.
class DriverMap extends StatefulWidget {
  const DriverMap({super.key, required this.pins, this.me, this.fallbackCenter, this.interactive = true});

  final List<MapPin> pins;

  /// The driver's own position, drawn as a pulsing dot.
  final LatLng? me;
  final LatLng? fallbackCenter;
  final bool interactive;

  /// Dar es Salaam city centre, used before any fix arrives.
  static const darEsSalaam = LatLng(-6.8160, 39.2803);

  @override
  State<DriverMap> createState() => _DriverMapState();
}

class _DriverMapState extends State<DriverMap> {
  final _controller = MapController();
  bool _ready = false;
  String? _fittedFor;

  List<LatLng> get _points => [...widget.pins.map((p) => p.point), ?widget.me];

  @override
  void didUpdateWidget(covariant DriverMap oldWidget) {
    super.didUpdateWidget(oldWidget);
    _fitIfChanged();
  }

  /// Refit only when the set of pins changes, never on every position tick,
  /// so the driver can pan without the map snapping back.
  void _fitIfChanged() {
    if (!_ready) return;
    final key = widget.pins.map((p) => '${p.point.latitude},${p.point.longitude}').join('|') + (widget.me == null ? '' : '+me');
    if (key == _fittedFor) return;
    _fittedFor = key;
    final points = _points;
    if (points.isEmpty) return;
    if (points.length == 1) {
      _controller.move(points.first, 15);
    } else {
      _controller.fitCamera(CameraFit.coordinates(coordinates: points, padding: const EdgeInsets.all(56), maxZoom: 16));
    }
  }

  @override
  Widget build(BuildContext context) {
    final center = widget.me ?? (widget.pins.isNotEmpty ? widget.pins.first.point : (widget.fallbackCenter ?? DriverMap.darEsSalaam));
    return FlutterMap(
      mapController: _controller,
      options: MapOptions(
        initialCenter: center,
        initialZoom: 14,
        interactionOptions: InteractionOptions(flags: widget.interactive ? InteractiveFlag.all & ~InteractiveFlag.rotate : InteractiveFlag.none),
        onMapReady: () {
          _ready = true;
          _fitIfChanged();
        },
      ),
      children: [
        TileLayer(
          urlTemplate: 'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
          userAgentPackageName: 'tz.co.sokoni.driver',
          maxNativeZoom: 18,
        ),
        MarkerLayer(
          markers: [
            for (final pin in widget.pins)
              Marker(
                point: pin.point,
                width: 44,
                height: 52,
                alignment: Alignment.topCenter,
                child: _Pin(pin: pin),
              ),
            if (widget.me != null)
              Marker(point: widget.me!, width: 28, height: 28, child: const _MeDot()),
          ],
        ),
        const RichAttributionWidget(attributions: [TextSourceAttribution('OpenStreetMap contributors')]),
      ],
    );
  }
}

class _Pin extends StatelessWidget {
  const _Pin({required this.pin});

  final MapPin pin;

  @override
  Widget build(BuildContext context) {
    return Semantics(
      label: pin.label,
      child: Column(
        children: [
          Container(
            width: 40,
            height: 40,
            decoration: BoxDecoration(
              color: pin.highlight ? SokoniColors.sokoniYellow : SokoniColors.sokoniBlack,
              shape: BoxShape.circle,
              border: Border.all(color: Colors.white, width: 2),
            ),
            child: Icon(pin.icon, size: 20, color: pin.highlight ? SokoniColors.onYellow : Colors.white),
          ),
          Container(width: 3, height: 10, color: SokoniColors.sokoniBlack),
        ],
      ),
    );
  }
}

class _MeDot extends StatefulWidget {
  const _MeDot();

  @override
  State<_MeDot> createState() => _MeDotState();
}

class _MeDotState extends State<_MeDot> with SingleTickerProviderStateMixin {
  late final AnimationController _pulse = AnimationController(vsync: this, duration: const Duration(milliseconds: 1400));

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (!MediaQuery.of(context).disableAnimations && !_pulse.isAnimating) _pulse.repeat();
  }

  @override
  void dispose() {
    _pulse.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return AnimatedBuilder(
      animation: _pulse,
      builder: (context, _) => Stack(
        alignment: Alignment.center,
        children: [
          Container(
            width: 12 + 16 * _pulse.value,
            height: 12 + 16 * _pulse.value,
            decoration: BoxDecoration(
              shape: BoxShape.circle,
              color: SokoniColors.sokoniYellow.withValues(alpha: 0.5 * (1 - _pulse.value)),
            ),
          ),
          Container(
            width: 14,
            height: 14,
            decoration: BoxDecoration(
              shape: BoxShape.circle,
              color: SokoniColors.sokoniBlack,
              border: Border.all(color: SokoniColors.sokoniYellow, width: 3),
            ),
          ),
        ],
      ),
    );
  }
}
