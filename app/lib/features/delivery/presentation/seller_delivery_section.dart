import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/l10n/gen/app_localizations.dart';
import '../../../core/theme/dimens.dart';
import '../../../data/models/order.dart';
import '../providers/delivery_providers.dart';
import 'delivery_labels.dart';
import 'widgets/delivery_widgets.dart';

/// The seller's view of a Sokoni delivery: what the rider is doing, the
/// rider card (phone only until pickup, by the server's rules) and the
/// pickup code to give the rider at handover. The seller view from the
/// server carries no PIN, no fee, no drop-off and no customer phone, and
/// this widget shows none.
class SellerDeliverySection extends ConsumerWidget {
  const SellerDeliverySection({required this.order, super.key});

  final Order order;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = AppLocalizations.of(context);
    final theme = Theme.of(context);
    final trackingAsync = ref.watch(deliveryTrackingProvider(order.id));

    return trackingAsync.when(
      skipLoadingOnRefresh: true,
      loading: () => const DeliveryCard(child: LinearProgressIndicator()),
      error: (error, _) => DeliveryCard(
        child: Row(
          children: [
            Expanded(child: Text(deliveryErrorMessage(l10n, error))),
            TextButton(onPressed: () => ref.invalidate(deliveryTrackingProvider(order.id)), child: Text(l10n.commonRetry)),
          ],
        ),
      ),
      data: (tracking) {
        if (tracking == null) {
          if (order.status != 'pending') return const SizedBox.shrink();
          return DeliveryCard(
            title: l10n.sellerDeliveryTitle,
            child: Text(l10n.sellerDeliveryAcceptHint),
          );
        }
        return Column(
          children: [
            DeliveryCard(
              title: l10n.sellerDeliveryTitle,
              child: Row(
                children: [
                  const Icon(Icons.two_wheeler_rounded),
                  const SizedBox(width: SokoniDimens.space12),
                  Expanded(child: Text(sellerDeliveryStatusLabel(l10n, tracking.status), key: const Key('seller-delivery-status'))),
                ],
              ),
            ),
            if (tracking.pickupCode != null)
              DeliveryCard(
                highlight: true,
                title: l10n.sellerPickupCodeTitle,
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      tracking.pickupCode!,
                      key: const Key('pickup-code'),
                      style: theme.textTheme.displaySmall?.copyWith(fontWeight: FontWeight.w800, letterSpacing: 10),
                    ),
                    const SizedBox(height: SokoniDimens.space8),
                    Text(l10n.sellerPickupCodeBody),
                  ],
                ),
              ),
            if (tracking.driver != null) DriverCard(driver: tracking.driver!, title: l10n.deliveryMapRider),
            if (!{'pending_payment', 'cancelled'}.contains(tracking.status))
              DeliveryCard(title: l10n.deliveryTimelineTitle, child: DeliveryProgress(tracking: tracking)),
          ],
        );
      },
    );
  }
}
