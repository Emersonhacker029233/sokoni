import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:geocoding/geocoding.dart';
import 'package:google_maps_flutter/google_maps_flutter.dart';

import '../../../../core/l10n/gen/app_localizations.dart';
import '../../../../core/location/location_service.dart';
import '../../../../core/network/api_exception.dart';
import '../../../../core/providers.dart';
import '../../../../core/theme/dimens.dart';
import '../../../../data/models/seller_profile.dart';
import '../../providers/seller_onboarding_providers.dart';

/// Step 2: map pin + reverse-geocoded, user-confirmed address (CLAUDE.md
/// feature 4). The pin starts at the device's current/cached position and
/// the user drags it to their shop's exact location.
class OnboardingStep2Location extends ConsumerStatefulWidget {
  const OnboardingStep2Location({required this.sellerId, required this.onSubmitted, super.key});

  final int? sellerId;
  final ValueChanged<SellerProfile> onSubmitted;

  @override
  ConsumerState<OnboardingStep2Location> createState() => _OnboardingStep2LocationState();
}

class _OnboardingStep2LocationState extends ConsumerState<OnboardingStep2Location> {
  static const _fallback = LatLng(-6.7924, 39.2083); // Dar es Salaam centre.

  LatLng _pin = _fallback;
  final _addressController = TextEditingController();
  final _regionController = TextEditingController();
  final _districtController = TextEditingController();
  bool _submitting = false;
  bool _resolving = false;
  String? _error;
  GoogleMapController? _mapController;
  final _geocoding = Geocoding();

  @override
  void initState() {
    super.initState();
    _loadInitialPosition();
  }

  @override
  void dispose() {
    _addressController.dispose();
    _regionController.dispose();
    _districtController.dispose();
    super.dispose();
  }

  Future<void> _loadInitialPosition() async {
    final result = await ref.read(locationServiceProvider).current();
    if (!mounted) return;
    if (result is LocationAvailable) {
      setState(() => _pin = LatLng(result.lat, result.lng));
      await _mapController?.animateCamera(CameraUpdate.newLatLng(_pin));
      await _reverseGeocode(_pin);
    }
  }

  Future<void> _reverseGeocode(LatLng position) async {
    setState(() => _resolving = true);
    try {
      final placemarks = await _geocoding.placemarkFromCoordinates(position.latitude, position.longitude);
      if (placemarks.isNotEmpty && mounted) {
        final place = placemarks.first;
        setState(() {
          _addressController.text = [
            place.street,
            place.subLocality,
          ].where((s) => s != null && s.isNotEmpty).join(', ');
          _regionController.text = place.administrativeArea ?? _regionController.text;
          _districtController.text = place.subAdministrativeArea ?? place.locality ?? _districtController.text;
        });
      }
    } catch (_) {
      // Geocoding can legitimately fail (no network, no result) — the
      // user can still fill the address fields in by hand.
    } finally {
      if (mounted) setState(() => _resolving = false);
    }
  }

  Future<void> _submit() async {
    if (widget.sellerId == null) return;
    if (_addressController.text.trim().isEmpty ||
        _regionController.text.trim().isEmpty ||
        _districtController.text.trim().isEmpty) {
      setState(() => _error = 'Please fill in all fields');
      return;
    }
    setState(() {
      _submitting = true;
      _error = null;
    });
    try {
      final seller = await ref.read(sellerOnboardingProvider.notifier).submitLocation(
        sellerId: widget.sellerId!,
        lat: _pin.latitude,
        lng: _pin.longitude,
        address: _addressController.text.trim(),
        region: _regionController.text.trim(),
        district: _districtController.text.trim(),
      );
      widget.onSubmitted(seller);
    } on ApiException catch (e) {
      setState(() => _error = e.message);
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);

    return Column(
      children: [
        Padding(
          padding: const EdgeInsets.all(SokoniDimens.space16),
          child: Text(l10n.onboardingLocationInstructions),
        ),
        Expanded(
          flex: 3,
          child: Stack(
            children: [
              GoogleMap(
                initialCameraPosition: CameraPosition(target: _pin, zoom: 15),
                onMapCreated: (controller) => _mapController = controller,
                onCameraMove: (position) => _pin = position.target,
                onCameraIdle: () => _reverseGeocode(_pin),
              ),
              const Center(
                child: Icon(Icons.location_pin, size: 48, color: Colors.red),
              ),
              if (_resolving)
                const Positioned(top: 12, right: 12, child: CircularProgressIndicator()),
            ],
          ),
        ),
        Expanded(
          flex: 4,
          child: SingleChildScrollView(
            padding: const EdgeInsets.all(SokoniDimens.space16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                TextField(
                  controller: _addressController,
                  decoration: InputDecoration(labelText: l10n.onboardingAddress),
                ),
                const SizedBox(height: SokoniDimens.space12),
                TextField(
                  controller: _regionController,
                  decoration: InputDecoration(labelText: l10n.onboardingRegion),
                ),
                const SizedBox(height: SokoniDimens.space12),
                TextField(
                  controller: _districtController,
                  decoration: InputDecoration(labelText: l10n.onboardingDistrict),
                ),
                if (_error != null) ...[
                  const SizedBox(height: SokoniDimens.space8),
                  Text(_error!, style: const TextStyle(color: Colors.red)),
                ],
                const SizedBox(height: SokoniDimens.space16),
                FilledButton(
                  onPressed: _submitting ? null : _submit,
                  child: _submitting
                      ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2))
                      : Text(l10n.onboardingConfirmLocation),
                ),
              ],
            ),
          ),
        ),
      ],
    );
  }
}
