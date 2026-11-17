import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../../../core/l10n/gen/app_localizations.dart';
import '../../../../core/theme/colors.dart';
import '../../../../core/theme/dimens.dart';
import '../../../../data/models/delivery.dart';
import '../../../../shared/widgets/sokoni_avatar.dart';
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

/// A one-line notice with an icon, the shape every delivery and return message uses.
class DeliveryNotice extends StatelessWidget {
  const DeliveryNotice({required this.icon, required this.text, this.title, this.danger = false, super.key});

  final IconData icon;
  final String text;
  final String? title;
  final bool danger;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return DeliveryCard(
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(icon, color: danger ? SokoniColors.danger : null),
          const SizedBox(width: SokoniDimens.space12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                if (title != null) Text(title!, style: theme.textTheme.titleSmall?.copyWith(fontWeight: FontWeight.w700)),
                Text(text),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

/// The delivery steps from "shop packing" to "delivered", with the time
/// each was reached (from the server's shared timeline).
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
  });

  final String label;
  final DateTime? time;
  final _StageState state;
  final bool isLast;
  final DateFormat formatter;
  final ThemeData theme;

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
              child: Text(
                label,
                style: theme.textTheme.bodyMedium?.copyWith(
                  fontWeight: state == _StageState.current ? FontWeight.w700 : FontWeight.w500,
                  color: state == _StageState.upcoming ? muted : null,
                ),
              ),
            ),
          ),
          if (time != null) Text(formatter.format(time!), style: theme.textTheme.bodySmall),
        ],
      ),
    );
  }
}

/// The Bolt driver: photo, name, car and plate, a call button while the
/// server includes the phone, and Bolt's live tracking page while the ride
/// is running.
class BoltDriverCard extends StatelessWidget {
  const BoltDriverCard({required this.driver, this.trackingUrl, this.title, super.key});

  final BoltDriver driver;
  final String? trackingUrl;
  final String? title;

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    final theme = Theme.of(context);
    final vehicle = boltVehicleSummary(driver);
    final eta = driver.etaSeconds;
    return DeliveryCard(
      title: title ?? l10n.deliveryBoltDriverTitle,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            children: [
              SokoniAvatar(imageUrl: driver.photoUrl, radius: 28, fallbackIcon: Icons.person_rounded),
              const SizedBox(width: SokoniDimens.space12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(driver.name ?? l10n.deliveryBoltDriverTitle, style: theme.textTheme.titleSmall),
                    if (vehicle.isNotEmpty) Text(vehicle, style: theme.textTheme.bodySmall),
                    if (driver.plate != null)
                      Container(
                        margin: const EdgeInsets.only(top: SokoniDimens.space4),
                        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                        decoration: BoxDecoration(color: SokoniColors.sokoniYellow, borderRadius: BorderRadius.circular(6)),
                        child: Text(
                          driver.plate!,
                          key: const Key('bolt-plate'),
                          style: const TextStyle(color: SokoniColors.onYellow, fontWeight: FontWeight.w800, letterSpacing: 1),
                        ),
                      ),
                    if (eta != null && eta > 0)
                      Text(l10n.deliveryBoltEta((eta / 60).ceil()), style: theme.textTheme.bodySmall),
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
          if (trackingUrl != null) ...[
            const SizedBox(height: SokoniDimens.space12),
            OutlinedButton.icon(
              key: const Key('bolt-tracking'),
              onPressed: () => launchUrl(Uri.parse(trackingUrl!), mode: LaunchMode.externalApplication),
              icon: const Icon(Icons.map_outlined),
              label: Text(l10n.deliveryBoltTrack),
            ),
          ],
        ],
      ),
    );
  }
}
