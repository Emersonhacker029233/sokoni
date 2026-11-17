import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_map/flutter_map.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:geocoding/geocoding.dart';
import 'package:go_router/go_router.dart';
import 'package:latlong2/latlong.dart';

import '../../../core/l10n/gen/app_localizations.dart';
import '../../../core/location/location_service.dart';
import '../../../core/providers.dart';
import '../../../core/theme/colors.dart';
import '../../../core/theme/dimens.dart';

/// A delivery drop-off: exact coordinates plus the readable address the
/// Bolt driver sees.
class PickedDeliveryLocation {
  const PickedDeliveryLocation({required this.lat, required this.lng, required this.address});

  final double lat;
  final double lng;
  final String address;
}

/// Full-screen map pin for the Sokoni 1.1 delivery checkout. OpenStreetMap
/// through flutter_map (no Google Maps key, like the shop-location step of
/// seller onboarding). The pin stays in the centre; the buyer moves the map
/// under it, or jumps to their GPS position. The address is reverse-
/// geocoded when the map settles and stays editable, because the driver
/// needs the house, gate or landmark that no geocoder knows.
class DeliveryLocationPickerScreen extends ConsumerStatefulWidget {
  const DeliveryLocationPickerScreen({this.initial, super.key});

  final PickedDeliveryLocation? initial;

  @override
  ConsumerState<DeliveryLocationPickerScreen> createState() => _DeliveryLocationPickerScreenState();
}

class _DeliveryLocationPickerScreenState extends ConsumerState<DeliveryLocationPickerScreen> {
  static const _darEsSalaam = LatLng(-6.7924, 39.2083);

  final _mapController = MapController();
  final _addressController = TextEditingController();
  final _geocoding = Geocoding();
  late LatLng _pin;
  Timer? _settle;
  bool _resolving = false;
  bool _locating = false;
  String? _notice;

  @override
  void initState() {
    super.initState();
    final initial = widget.initial;
    _pin = initial == null ? _darEsSalaam : LatLng(initial.lat, initial.lng);
    _addressController.text = initial?.address ?? '';
    if (initial == null) WidgetsBinding.instance.addPostFrameCallback((_) => _useMyLocation());
  }

  @override
  void dispose() {
    _settle?.cancel();
    _addressController.dispose();
    super.dispose();
  }

  Future<void> _useMyLocation() async {
    setState(() {
      _locating = true;
      _notice = null;
    });
    final result = await ref.read(locationServiceProvider).current();
    if (!mounted) return;
    setState(() => _locating = false);
    if (result is LocationAvailable) {
      _pin = LatLng(result.lat, result.lng);
      _mapController.move(_pin, 17);
      await _reverseGeocode();
    } else {
      setState(() => _notice = AppLocalizations.of(context).deliveryPickerLocationOff);
    }
  }

  void _onPositionChanged(MapCamera camera, bool hasGesture) {
    _pin = camera.center;
    if (!hasGesture) return;
    _settle?.cancel();
    _settle = Timer(const Duration(milliseconds: 600), _reverseGeocode);
  }

  Future<void> _reverseGeocode() async {
    setState(() => _resolving = true);
    try {
      final places = await _geocoding.placemarkFromCoordinates(_pin.latitude, _pin.longitude);
      if (places.isNotEmpty && mounted) {
        final place = places.first;
        final text = [place.street, place.subLocality, place.locality]
            .whereType<String>()
            .where((s) => s.trim().isNotEmpty)
            .toSet()
            .join(', ');
        if (text.isNotEmpty) _addressController.text = text;
      }
    } catch (_) {
      // No network or no result: the buyer types the address themselves.
    } finally {
      if (mounted) setState(() => _resolving = false);
    }
  }

  void _confirm() {
    final address = _addressController.text.trim();
    if (address.isEmpty) {
      setState(() => _notice = AppLocalizations.of(context).checkoutAddressRequired);
      return;
    }
    context.pop(PickedDeliveryLocation(lat: _pin.latitude, lng: _pin.longitude, address: address));
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    final theme = Theme.of(context);

    return Scaffold(
      appBar: AppBar(title: Text(l10n.deliveryPickerTitle)),
      body: Column(
        children: [
          Expanded(
            child: Stack(
              children: [
                FlutterMap(
                  mapController: _mapController,
                  options: MapOptions(
                    initialCenter: _pin,
                    initialZoom: widget.initial == null ? 13 : 17,
                    maxZoom: 19,
                    onPositionChanged: _onPositionChanged,
                  ),
                  children: [
                    TileLayer(
                      urlTemplate: 'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
                      userAgentPackageName: 'tz.co.sokoni.sokoni',
                      maxNativeZoom: 19,
                    ),
                    const SimpleAttributionWidget(source: Text('OpenStreetMap')),
                  ],
                ),
                // The pin's tip sits on the map centre.
                IgnorePointer(
                  child: Center(
                    child: Padding(
                      padding: const EdgeInsets.only(bottom: 44),
                      child: Icon(Icons.location_on_rounded, size: 48, color: SokoniColors.sokoniBlack, semanticLabel: l10n.deliveryPickerTitle),
                    ),
                  ),
                ),
                Positioned(
                  left: SokoniDimens.space16,
                  right: SokoniDimens.space16,
                  top: SokoniDimens.space12,
                  child: Material(
                    color: theme.colorScheme.surface,
                    borderRadius: BorderRadius.circular(12),
                    child: Padding(
                      padding: const EdgeInsets.all(SokoniDimens.space12),
                      child: Text(l10n.deliveryPickerInstructions, style: theme.textTheme.bodySmall),
                    ),
                  ),
                ),
                Positioned(
                  right: SokoniDimens.space16,
                  bottom: SokoniDimens.space16,
                  child: FloatingActionButton.extended(
                    heroTag: 'delivery-locate',
                    onPressed: _locating ? null : _useMyLocation,
                    icon: _locating
                        ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2))
                        : const Icon(Icons.my_location_rounded),
                    label: Text(l10n.deliveryPickerUseMyLocation),
                  ),
                ),
              ],
            ),
          ),
          SafeArea(
            top: false,
            child: Padding(
              padding: EdgeInsets.fromLTRB(
                SokoniDimens.space16,
                SokoniDimens.space16,
                SokoniDimens.space16,
                SokoniDimens.space16 + MediaQuery.viewInsetsOf(context).bottom,
              ),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  TextField(
                    controller: _addressController,
                    minLines: 1,
                    maxLines: 3,
                    textCapitalization: TextCapitalization.sentences,
                    decoration: InputDecoration(
                      labelText: l10n.checkoutAddressDetails,
                      helperText: _resolving ? l10n.deliveryPickerFindingAddress : null,
                    ),
                  ),
                  if (_notice != null) ...[
                    const SizedBox(height: SokoniDimens.space8),
                    Text(_notice!, style: theme.textTheme.bodySmall?.copyWith(color: SokoniColors.danger)),
                  ],
                  const SizedBox(height: SokoniDimens.space12),
                  FilledButton(onPressed: _confirm, child: Text(l10n.deliveryPickerConfirm)),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}
