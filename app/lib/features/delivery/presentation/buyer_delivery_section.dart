import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/l10n/gen/app_localizations.dart';
import '../../../core/router/routes.dart';
import '../../../core/theme/dimens.dart';
import '../../../core/utils/formatters.dart';
import '../../../data/models/delivery.dart';
import '../../../data/models/order.dart';
import '../providers/delivery_providers.dart';
import 'delivery_labels.dart';
import 'return_section.dart';
import 'widgets/delivery_widgets.dart';
import 'widgets/pay_now_card.dart';

/// The buyer's view of a Bolt delivery on the order screen: waiting for the
/// shop, Pay now, the shop packing, Bolt finding a driver, the driver card
/// with Bolt's tracking link, problems with their choices, and the return
/// once delivered. Everything shown comes from `GET /delivery/orders/{order}`,
/// polled.
class BuyerDeliverySection extends ConsumerWidget {
  const BuyerDeliverySection({required this.order, super.key});

  final Order order;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = AppLocalizations.of(context);
    final trackingAsync = ref.watch(deliveryTrackingProvider(order.id));
    final timeout = ref.watch(deliveryAvailabilityProvider).value?.unpaidTimeoutMinutes ?? 20;

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
          if (order.status == 'cancelled') return const SizedBox.shrink();
          return DeliveryNotice(icon: Icons.hourglass_top_rounded, title: l10n.deliveryTimelineTitle, text: l10n.deliveryWaitingForShop);
        }
        return _Body(tracking: tracking, unpaidTimeoutMinutes: timeout);
      },
    );
  }
}

class _Body extends ConsumerStatefulWidget {
  const _Body({required this.tracking, required this.unpaidTimeoutMinutes});

  final DeliveryTracking tracking;
  final int unpaidTimeoutMinutes;

  @override
  ConsumerState<_Body> createState() => _BodyState();
}

class _BodyState extends ConsumerState<_Body> {
  bool _busy = false;

  Future<void> _run(Future<void> Function(DeliveryTrackingController c) action, {String? confirm}) async {
    final l10n = AppLocalizations.of(context);
    if (confirm != null) {
      final ok = await showDialog<bool>(
        context: context,
        builder: (context) => AlertDialog(
          content: Text(confirm),
          actions: [
            TextButton(onPressed: () => Navigator.of(context).pop(false), child: Text(l10n.deliveryKeep)),
            TextButton(onPressed: () => Navigator.of(context).pop(true), child: Text(l10n.deliveryConfirmYes)),
          ],
        ),
      );
      if (ok != true) return;
    }
    setState(() => _busy = true);
    try {
      await action(ref.read(deliveryTrackingProvider(widget.tracking.orderId).notifier));
    } catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(deliveryErrorMessage(l10n, e))));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    final theme = Theme.of(context);
    final t = widget.tracking;
    final payment = t.payment;
    final refund = refundMessage(l10n, t.refund);

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        if (t.status == 'pending_quote') DeliveryNotice(icon: Icons.hourglass_top_rounded, text: l10n.deliveryBoltPricing),

        if (t.awaitingPayment) PayNowCard(tracking: t),

        if (t.status == 'awaiting_ready') DeliveryNotice(icon: Icons.inventory_2_outlined, text: l10n.deliveryBoltWaitingForShop),

        if (t.driverChanged) DeliveryNotice(icon: Icons.sync_rounded, text: l10n.deliveryDriverChanged),

        if (t.status == 'no_driver_found' || t.status == 'failed')
          _Problem(
            title: t.status == 'failed' ? l10n.deliveryFailedTitle : l10n.deliveryNoDriverTitle,
            body: deliveryProblemBody(l10n, t.failureReason),
            actions: t.actions,
            busy: _busy,
            onRetry: () => _run((c) => c.retry()),
            onSwitch: () => _run((c) => c.switchToPickup(), confirm: l10n.deliverySwitchConfirm),
            onCancel: () => _run((c) => c.cancel(), confirm: l10n.deliveryCancelConfirm),
          ),

        if (t.status == 'cancelled')
          DeliveryNotice(
            icon: Icons.cancel_outlined,
            title: l10n.deliveryCancelledTitle,
            text: cancelledMessage(l10n, t, unpaidTimeoutMinutes: widget.unpaidTimeoutMinutes),
            danger: true,
          ),

        if (refund != null) DeliveryNotice(icon: Icons.replay_rounded, text: refund),

        if (payment?.receipt != null)
          DeliveryNotice(icon: Icons.receipt_long_rounded, text: l10n.deliveryPaidReceipt(SokoniFormat.tzs(payment!.receipt!.amount))),

        if (t.driver != null) BoltDriverCard(driver: t.driver!, trackingUrl: t.trackingUrl),

        // The in-app map, from driver assigned until Bolt completes the ride.
        if (t.liveLocation)
          Padding(
            padding: const EdgeInsets.only(bottom: SokoniDimens.space12),
            child: SizedBox(
              width: double.infinity,
              child: FilledButton.icon(
                key: const Key('open-live-tracking'),
                onPressed: () => context.push(SokoniRoutes.deliveryTracking(t.orderId)),
                icon: const Icon(Icons.map_rounded),
                label: Text(l10n.deliveryLiveOpen),
              ),
            ),
          ),

        if (!{'pending_quote', 'pending_payment', 'cancelled'}.contains(t.status))
          DeliveryCard(title: l10n.deliveryTimelineTitle, child: DeliveryProgress(tracking: t)),

        if (t.returnInfo != null) ReturnSection(tracking: t, isSeller: false),

        if (t.actions.canCancel && t.status != 'no_driver_found' && t.status != 'failed')
          SizedBox(
            width: double.infinity,
            child: OutlinedButton(
              key: const Key('cancel-delivery'),
              onPressed: _busy ? null : () => _run((c) => c.cancel(), confirm: l10n.deliveryCancelConfirm),
              child: Text(l10n.deliveryCancel),
            ),
          ),
        if (t.actions.canSwitchToPickup && (t.status == 'pending_payment' || t.status == 'awaiting_ready'))
          Center(
            child: TextButton(
              onPressed: _busy ? null : () => _run((c) => c.switchToPickup(), confirm: l10n.deliverySwitchConfirm),
              child: Text(l10n.deliverySwitchToPickup, style: theme.textTheme.bodySmall),
            ),
          ),
      ],
    );
  }
}

class _Problem extends StatelessWidget {
  const _Problem({
    required this.title,
    required this.body,
    required this.actions,
    required this.busy,
    required this.onRetry,
    required this.onSwitch,
    required this.onCancel,
  });

  final String title;
  final String body;
  final DeliveryActions actions;
  final bool busy;
  final VoidCallback onRetry;
  final VoidCallback onSwitch;
  final VoidCallback onCancel;

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    return DeliveryCard(
      title: title,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text(body),
          const SizedBox(height: SokoniDimens.space12),
          if (actions.canRetry) FilledButton(onPressed: busy ? null : onRetry, child: Text(l10n.deliveryRetry)),
          if (actions.canSwitchToPickup) OutlinedButton(onPressed: busy ? null : onSwitch, child: Text(l10n.deliverySwitchToPickup)),
          if (actions.canCancel) TextButton(onPressed: busy ? null : onCancel, child: Text(l10n.deliveryCancel)),
        ],
      ),
    );
  }
}
