import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/l10n/gen/app_localizations.dart';
import '../../../data/models/order.dart';
import '../providers/delivery_providers.dart';
import 'delivery_labels.dart';
import 'return_section.dart';
import 'widgets/delivery_widgets.dart';

/// The shop's view of a Bolt delivery: whether the buyer has paid (and so
/// whether to mark the order ready, which books Bolt), the Bolt driver
/// coming to collect (phone and tracking link only until pickup), and any
/// return. The seller view from the server carries no fee, no drop-off and
/// no customer phone, and this widget shows none.
class SellerDeliverySection extends ConsumerWidget {
  const SellerDeliverySection({required this.order, super.key});

  final Order order;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = AppLocalizations.of(context);
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
          return DeliveryCard(title: l10n.sellerDeliveryTitle, child: Text(l10n.sellerDeliveryAcceptHint));
        }
        return Column(
          children: [
            DeliveryCard(
              title: l10n.sellerDeliveryTitle,
              highlight: tracking.status == 'awaiting_ready',
              child: Row(
                children: [
                  const Icon(Icons.local_shipping_outlined),
                  const SizedBox(width: 12),
                  Expanded(child: Text(sellerDeliveryStatusLabel(l10n, tracking), key: const Key('seller-delivery-status'))),
                ],
              ),
            ),
            if (tracking.driver != null) BoltDriverCard(driver: tracking.driver!, trackingUrl: tracking.trackingUrl),
            if (!{'pending_quote', 'pending_payment', 'cancelled'}.contains(tracking.status))
              DeliveryCard(title: l10n.deliveryTimelineTitle, child: DeliveryProgress(tracking: tracking)),
            if (tracking.returnInfo?.request != null) ReturnSection(tracking: tracking, isSeller: true, buyer: order.buyer),
          ],
        );
      },
    );
  }
}
