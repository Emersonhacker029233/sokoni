import 'package:flutter/material.dart';
import 'package:flutter_map/flutter_map.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/intl.dart';
import 'package:latlong2/latlong.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../../../core/l10n/gen/app_localizations.dart';
import '../../../../core/theme/colors.dart';
import '../../../../core/theme/dimens.dart';
import '../../../../data/models/delivery.dart';
import '../../../../shared/widgets/sokoni_avatar.dart';
import '../../providers/delivery_providers.dart';
import '../delivery_labels.dart';

/// A bordered card, the shape every delivery block on the order screen uses.
class DeliveryCard extends StatelessWidget {
  const DeliveryCard({required this.child, this.title, this.highlight = false, super.key});

  final Widget child;
  final String? title;
  final bool highlight;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final dark = theme.brightness == Brightness.dark;
    return Container(
      width: double.infinity,
      margin: const EdgeInsets.only(bottom: SokoniDimens.space12),
      padding: const EdgeInsets.all(SokoniDimens.space16),
      decoration: BoxDecoration(
        color: highlight ? SokoniColors.sokoniYellow.withValues(alpha: dark ? 0.12 : 0.15) : theme.colorScheme.surface,
        border: Border.all(color: highlight ? SokoniColors.sokoniYellow : (dark ? SokoniColors.darkOutline : SokoniColors.outline)),
        borderRadius: BorderRadius.circular(16),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          if (title != null) ...[
            Text(title!, style: theme.textTheme.titleSmall?.copyWith(fontWeight: FontWeight.w700)),
            const SizedBox(height: SokoniDimens.space12),
          ],
          child,
        ],
      ),
    );
  }
}

/// The seven customer-facing steps, from "finding a driver" to "delivered",
/// with the time each was reached (from the server's shared timeline).
class DeliveryProgress extends StatelessWidget {
  const DeliveryProgress({required this.tracking, super.key});

  final DeliveryTracking tracking;

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    final theme = Theme.of(context);
    final reached = deliveryStageIndex(tracking);
    final problem = {'no_driver_found', 'failed', 'cancelled'}.contains(tracking.status);
    final time = DateFormat('HH:mm');

    return Column(
      children: [
        for (var i = 0; i < deliveryStages.length; i++)
          _StageRow(
            label: deliveryStageLabel(l10n, deliveryStages[i]),
            time: tracking.reachedAt(deliveryStages[i]),
            state: i < reached || (i == reached && (tracking.status == 'delivered' || problem))
                ? _StageState.done
                : i == reached
                ? _StageState.current
                : _StageState.upcoming,
            isLast: i == deliveryStages.length - 1,
            formatter: time,
            detail: i == 0 && tracking.status == 'pending_driver' && (tracking.dispatchWave ?? 0) > 0
                ? l10n.deliverySearchRound(tracking.dispatchWave!)
                : null,
            theme: theme,
          ),
      ],
    );
  }
}

enum _StageState { done, current, upcoming }

class _StageRow extends StatelessWidget {
  const _StageRow({
    required this.label,
    required this.time,
    required this.state,
    required this.isLast,
    required this.formatter,
    required this.theme,
    this.detail,
  });

  final String label;
  final DateTime? time;
  final _StageState state;
  final bool isLast;
  final DateFormat formatter;
  final ThemeData theme;
  final String? detail;

  @override
  Widget build(BuildContext context) {
    final muted = theme.hintColor;
    final dot = switch (state) {
      _StageState.done => const Icon(Icons.check_circle_rounded, size: 20, color: SokoniColors.success),
      _StageState.current => Container(
        width: 20,
        height: 20,
        decoration: BoxDecoration(
          color: SokoniColors.sokoniYellow,
          shape: BoxShape.circle,
          border: Border.all(color: SokoniColors.sokoniBlack, width: 2),
        ),
      ),
      _StageState.upcoming => Icon(Icons.radio_button_unchecked_rounded, size: 20, color: muted),
    };
    return IntrinsicHeight(
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SizedBox(
            width: 24,
            child: Column(
              children: [
                dot,
                if (!isLast)
                  Expanded(
                    child: Container(width: 2, color: state == _StageState.done ? SokoniColors.success : theme.dividerColor),
                  ),
              ],
            ),
          ),
          const SizedBox(width: SokoniDimens.space12),
          Expanded(
            child: Padding(
              padding: const EdgeInsets.only(bottom: SokoniDimens.space12),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    label,
                    style: theme.textTheme.bodyMedium?.copyWith(
                      fontWeight: state == _StageState.current ? FontWeight.w700 : FontWeight.w500,
                      color: state == _StageState.upcoming ? muted : null,
                    ),
                  ),
                  if (detail != null) Text(detail!, style: theme.textTheme.bodySmall),
                ],
              ),
            ),
          ),
          if (time != null) Text(formatter.format(time!), style: theme.textTheme.bodySmall),
        ],
      ),
    );
  }
}

/// Rider name, photo, rating, vehicle and plate, with a call button when
/// the server includes the phone (only during an active job).
class DriverCard extends StatelessWidget {
  const DriverCard({required this.driver, this.title, super.key});

  final DeliveryDriver driver;
  final String? title;

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    final theme = Theme.of(context);
    final vehicle = driver.vehicle;
    return DeliveryCard(
      title: title ?? l10n.deliveryDriverTitle,
      child: Row(
        children: [
          SokoniAvatar(imageUrl: driver.photoUrl, radius: 28, fallbackIcon: Icons.person_rounded),
          const SizedBox(width: SokoniDimens.space12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(driver.name, style: theme.textTheme.titleSmall),
                if (driver.ratingAvg != null && driver.ratingCount > 0)
                  Row(
                    children: [
                      const Icon(Icons.star_rounded, size: 16, color: SokoniColors.sokoniYellow),
                      const SizedBox(width: 2),
                      Text('${driver.ratingAvg!.toStringAsFixed(1)} (${driver.ratingCount})', style: theme.textTheme.bodySmall),
                    ],
                  ),
                if (vehicle != null) Text(vehicleSummary(l10n, vehicle), style: theme.textTheme.bodySmall),
                if (vehicle?.plateNumber != null)
                  Container(
                    margin: const EdgeInsets.only(top: SokoniDimens.space4),
                    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                    decoration: BoxDecoration(
                      color: SokoniColors.sokoniYellow,
                      borderRadius: BorderRadius.circular(6),
                    ),
                    child: Text(
                      vehicle!.plateNumber!,
                      style: const TextStyle(color: SokoniColors.onYellow, fontWeight: FontWeight.w800, letterSpacing: 1),
                    ),
                  ),
              ],
            ),
          ),
          if (driver.phone != null)
            FilledButton.tonalIcon(
              key: const Key('call-driver'),
              onPressed: () => launchUrl(Uri(scheme: 'tel', path: driver.phone)),
              icon: const Icon(Icons.call_rounded),
              label: Text(l10n.deliveryDriverCall),
            ),
        ],
      ),
    );
  }
}

/// The rider on a map with the shop and the drop-off. The position comes
/// from the Firebase live stream when the server has it configured, else
/// from the server's heartbeat in the polled delivery view.
class LiveDriverMap extends ConsumerWidget {
  const LiveDriverMap({required this.tracking, super.key});

  final DeliveryTracking tracking;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = AppLocalizations.of(context);
    final theme = Theme.of(context);
    final live = tracking.isActive ? ref.watch(liveDriverPositionProvider(tracking.id)).value : null;
    final rider = live ?? tracking.driverLocation;
    final shop = tracking.pickup;
    final dropoff = tracking.dropoff;

    final points = [
      if (shop != null) LatLng(shop.lat, shop.lng),
      if (dropoff != null) LatLng(dropoff.lat, dropoff.lng),
      if (rider != null) LatLng(rider.lat, rider.lng),
    ];
    if (points.isEmpty) return const SizedBox.shrink();
    final center = rider != null ? LatLng(rider.lat, rider.lng) : points.first;

    return DeliveryCard(
      title: l10n.deliveryMapTitle,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          ClipRRect(
            borderRadius: BorderRadius.circular(12),
            child: SizedBox(
              height: 220,
              child: FlutterMap(
                // A new key when the rider moves far re-centres the map.
                key: ValueKey('${center.latitude.toStringAsFixed(3)},${center.longitude.toStringAsFixed(3)}'),
                options: MapOptions(
                  initialCenter: center,
                  initialZoom: 14,
                  interactionOptions: const InteractionOptions(flags: InteractiveFlag.pinchZoom | InteractiveFlag.drag),
                ),
                children: [
                  TileLayer(
                    urlTemplate: 'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
                    userAgentPackageName: 'tz.co.sokoni.sokoni',
                    maxNativeZoom: 19,
                  ),
                  MarkerLayer(
                    markers: [
                      if (shop != null) _marker(LatLng(shop.lat, shop.lng), Icons.storefront_rounded, l10n.deliveryMapShop, SokoniColors.sokoniBlack),
                      if (dropoff != null) _marker(LatLng(dropoff.lat, dropoff.lng), Icons.home_rounded, l10n.deliveryMapYou, SokoniColors.success),
                      if (rider != null) _marker(LatLng(rider.lat, rider.lng), Icons.two_wheeler_rounded, l10n.deliveryMapRider, SokoniColors.sokoniYellow),
                    ],
                  ),
                  const SimpleAttributionWidget(source: Text('OpenStreetMap')),
                ],
              ),
            ),
          ),
          const SizedBox(height: SokoniDimens.space8),
          Text(
            rider == null
                ? l10n.deliveryMapWaiting
                : l10n.deliveryMapUpdated(rider.at == null ? '—' : DateFormat('HH:mm:ss').format(rider.at!)),
            style: theme.textTheme.bodySmall,
          ),
        ],
      ),
    );
  }

  Marker _marker(LatLng point, IconData icon, String label, Color color) => Marker(
    point: point,
    width: 40,
    height: 40,
    child: Semantics(
      label: label,
      child: Container(
        decoration: BoxDecoration(
          color: color,
          shape: BoxShape.circle,
          border: Border.all(color: Colors.white, width: 2),
        ),
        child: Icon(icon, size: 20, color: color == SokoniColors.sokoniYellow ? SokoniColors.onYellow : Colors.white),
      ),
    ),
  );
}
