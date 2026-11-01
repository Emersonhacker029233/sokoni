import 'package:flutter/material.dart';

import '../../core/l10n/gen/app_localizations.dart';
import '../../core/theme/colors.dart';
import '../../core/theme/dimens.dart';
import '../../core/utils/formatters.dart';
import '../../data/models/delivery_offer.dart';
import '../../shared/widgets/common.dart';

/// One delivery request: pay first and biggest, then distance, shop and
/// rough drop-off area. No customer details until accepted.
class OfferCard extends StatelessWidget {
  const OfferCard({
    super.key,
    required this.offer,
    required this.onAccept,
    required this.onDecline,
    this.accepting = false,
    this.moreCount = 0,
  });

  final DeliveryOffer offer;
  final VoidCallback onAccept;
  final VoidCallback onDecline;
  final bool accepting;
  final int moreCount;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final theme = Theme.of(context);
    return Container(
      padding: const EdgeInsets.all(SokoniDimens.space20),
      decoration: BoxDecoration(
        color: theme.colorScheme.surface,
        borderRadius: BorderRadius.circular(SokoniDimens.radiusSheet),
        border: Border.all(color: SokoniColors.sokoniYellow, width: 2),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: SokoniDimens.ambientShadowOpacity * 3),
            blurRadius: SokoniDimens.ambientBlurRadius,
          ),
        ],
      ),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                width: 32,
                height: 32,
                decoration: const BoxDecoration(color: SokoniColors.sokoniYellow, shape: BoxShape.circle),
                child: const Icon(Icons.notifications_active_rounded, size: 18, color: SokoniColors.onYellow),
              ),
              const SizedBox(width: 8),
              Expanded(child: Text(l.newRequest, style: theme.textTheme.titleMedium)),
              if (moreCount > 0) Text(l.moreRequests(moreCount), style: theme.textTheme.bodySmall),
            ],
          ),
          const SizedBox(height: SokoniDimens.space16),
          Text(l.youEarn, style: theme.textTheme.bodySmall),
          Text(DriverFormat.tzs(offer.driverEarnings), style: theme.textTheme.displayLarge),
          const SizedBox(height: SokoniDimens.space12),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              Pill(icon: Icons.storefront_outlined, label: l.toShop(DriverFormat.km(offer.distanceToShopKm))),
              Pill(icon: Icons.route_outlined, label: l.tripDistance(DriverFormat.km(offer.tripKm))),
              if (offer.itemsCount > 0) Pill(icon: Icons.inventory_2_outlined, label: l.itemsCount(offer.itemsCount)),
            ],
          ),
          const SizedBox(height: SokoniDimens.space16),
          _Place(
            icon: Icons.storefront_rounded,
            label: l.pickupFrom,
            value: [offer.shop.name, offer.shop.district].whereType<String>().where((s) => s.isNotEmpty).join(', '),
          ),
          const SizedBox(height: SokoniDimens.space8),
          _Place(icon: Icons.location_on_outlined, label: l.dropoffArea, value: l.dropoffAreaHint),
          if (offer.collectCash > 0) ...[
            const SizedBox(height: SokoniDimens.space12),
            NoticeBanner(icon: Icons.payments_outlined, text: l.collectCash(DriverFormat.tzs(offer.collectCash))),
          ],
          const SizedBox(height: SokoniDimens.space20),
          Row(
            children: [
              Expanded(
                child: OutlinedButton(onPressed: accepting ? null : onDecline, child: Text(l.decline)),
              ),
              const SizedBox(width: SokoniDimens.space12),
              Expanded(
                flex: 2,
                child: PrimaryButton(label: l.accept, loading: accepting, onPressed: onAccept, icon: Icons.check_rounded),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _Place extends StatelessWidget {
  const _Place({required this.icon, required this.label, required this.value});

  final IconData icon;
  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Icon(icon, size: 20),
        const SizedBox(width: 10),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(label, style: theme.textTheme.bodySmall),
              Text(value, style: theme.textTheme.bodyMedium?.copyWith(fontWeight: FontWeight.w600)),
            ],
          ),
        ),
      ],
    );
  }
}
