import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/intl.dart';

import '../../../core/l10n/gen/app_localizations.dart';
import '../../../core/network/api_exception.dart';
import '../../../core/theme/colors.dart';
import '../../../core/theme/dimens.dart';
import '../../../core/utils/formatters.dart';
import '../../../data/models/order.dart';
import '../../../shared/widgets/error_state.dart';
import '../../auth/providers/auth_providers.dart';
import '../providers/order_providers.dart';

/// Next forward status for each current status — drives the seller's
/// single "advance" action button (CLAUDE.md feature 8: pending → accepted
/// → ready → completed, both parties see the same timeline).
const _nextStatus = {'pending': 'accepted', 'accepted': 'ready', 'ready': 'completed'};

class OrderDetailScreen extends ConsumerWidget {
  const OrderDetailScreen({required this.orderId, super.key});

  final int orderId;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = AppLocalizations.of(context);
    final orderAsync = ref.watch(orderDetailProvider(orderId));

    return Scaffold(
      appBar: AppBar(title: Text(l10n.orderDetailTitle)),
      body: orderAsync.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (error, _) => SokoniErrorState(
          message: error is ApiException ? error.message : '$error',
          onRetry: () => ref.invalidate(orderDetailProvider(orderId)),
        ),
        data: (order) => _OrderDetailBody(order: order),
      ),
    );
  }
}

class _OrderDetailBody extends ConsumerStatefulWidget {
  const _OrderDetailBody({required this.order});

  final Order order;

  @override
  ConsumerState<_OrderDetailBody> createState() => _OrderDetailBodyState();
}

class _OrderDetailBodyState extends ConsumerState<_OrderDetailBody> {
  bool _updating = false;

  Future<void> _advance(String status) async {
    setState(() => _updating = true);
    try {
      await ref.read(orderRepositoryProvider).updateStatus(orderId: widget.order.id, status: status);
      ref.invalidate(orderDetailProvider(widget.order.id));
    } on ApiException catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
      }
    } finally {
      if (mounted) setState(() => _updating = false);
    }
  }

  Future<void> _cancel() async {
    final l10n = AppLocalizations.of(context);
    final reasonController = TextEditingController();
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: Text(l10n.orderCancel),
        content: TextField(
          controller: reasonController,
          decoration: InputDecoration(labelText: l10n.orderCancelReason),
        ),
        actions: [
          TextButton(onPressed: () => Navigator.of(context).pop(false), child: Text(l10n.commonCancel)),
          TextButton(onPressed: () => Navigator.of(context).pop(true), child: Text(l10n.orderCancel)),
        ],
      ),
    );
    if (confirmed != true) return;
    setState(() => _updating = true);
    try {
      await ref
          .read(orderRepositoryProvider)
          .updateStatus(orderId: widget.order.id, status: 'cancelled', reason: reasonController.text.trim());
      ref.invalidate(orderDetailProvider(widget.order.id));
    } on ApiException catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
      }
    } finally {
      if (mounted) setState(() => _updating = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    final order = widget.order;
    final currentUserAsync = ref.watch(currentUserProvider);
    final isSeller = currentUserAsync.maybeWhen(
      data: (user) => user.id != order.buyer?.id,
      orElse: () => false,
    );

    return SingleChildScrollView(
      padding: const EdgeInsets.all(SokoniDimens.space20),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Text(order.code, style: Theme.of(context).textTheme.titleLarge),
              _StatusChip(status: order.status),
            ],
          ),
          const SizedBox(height: SokoniDimens.space20),
          Text(l10n.orderTimelineTitle, style: Theme.of(context).textTheme.titleSmall),
          const SizedBox(height: SokoniDimens.space8),
          _Timeline(steps: order.timeline),
          if (order.status == 'cancelled' && order.cancelledReason != null) ...[
            const SizedBox(height: SokoniDimens.space8),
            Text(order.cancelledReason!, style: Theme.of(context).textTheme.bodySmall),
          ],
          const SizedBox(height: SokoniDimens.space24),
          Text(l10n.orderItemsTitle, style: Theme.of(context).textTheme.titleSmall),
          const SizedBox(height: SokoniDimens.space8),
          for (final item in order.items)
            Padding(
              padding: const EdgeInsets.symmetric(vertical: SokoniDimens.space4),
              child: Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Expanded(child: Text('${item.qty}× ${item.title}')),
                  Text(SokoniFormat.tzs(item.lineTotal)),
                ],
              ),
            ),
          const Divider(),
          _SummaryRow(label: l10n.cartSubtotal, value: SokoniFormat.tzs(order.subtotal)),
          _SummaryRow(label: l10n.checkoutDeliveryFee, value: SokoniFormat.tzs(order.deliveryFee)),
          _SummaryRow(label: l10n.checkoutTotal, value: SokoniFormat.tzs(order.total), emphasize: true),
          const SizedBox(height: SokoniDimens.space20),
          Text(l10n.orderDeliveryMethod(order.deliveryMethod), style: Theme.of(context).textTheme.bodyMedium),
          if (order.address != null) Text(order.address!, style: Theme.of(context).textTheme.bodySmall),
          Text(l10n.orderPaymentMethod(order.paymentMethod), style: Theme.of(context).textTheme.bodyMedium),
          const SizedBox(height: SokoniDimens.space24),
          if (isSeller && _nextStatus.containsKey(order.status))
            FilledButton(
              onPressed: _updating ? null : () => _advance(_nextStatus[order.status]!),
              child: _updating
                  ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2))
                  : Text(l10n.orderAdvanceTo(_nextStatus[order.status]!)),
            ),
          if (order.status == 'pending' || order.status == 'accepted' || order.status == 'ready') ...[
            const SizedBox(height: SokoniDimens.space8),
            OutlinedButton(
              onPressed: _updating ? null : _cancel,
              child: Text(l10n.orderCancel),
            ),
          ],
        ],
      ),
    );
  }
}

class _StatusChip extends StatelessWidget {
  const _StatusChip({required this.status});

  final String status;

  @override
  Widget build(BuildContext context) {
    final color = switch (status) {
      'completed' => SokoniColors.success,
      'cancelled' => SokoniColors.danger,
      _ => SokoniColors.sokoniYellow,
    };
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: SokoniDimens.space12, vertical: SokoniDimens.space4),
      decoration: BoxDecoration(color: color.withValues(alpha: 0.15), borderRadius: BorderRadius.circular(999)),
      child: Text(status, style: TextStyle(color: color, fontWeight: FontWeight.w700)),
    );
  }
}

class _Timeline extends StatelessWidget {
  const _Timeline({required this.steps});

  final List<OrderTimelineStep> steps;

  @override
  Widget build(BuildContext context) {
    final formatter = DateFormat('d MMM, HH:mm');
    return Column(
      children: [
        for (final step in steps)
          Padding(
            padding: const EdgeInsets.symmetric(vertical: SokoniDimens.space4),
            child: Row(
              children: [
                const Icon(Icons.check_circle_rounded, size: 16, color: SokoniColors.success),
                const SizedBox(width: SokoniDimens.space8),
                Expanded(child: Text(step.status)),
                Text(formatter.format(step.at), style: Theme.of(context).textTheme.bodySmall),
              ],
            ),
          ),
      ],
    );
  }
}

class _SummaryRow extends StatelessWidget {
  const _SummaryRow({required this.label, required this.value, this.emphasize = false});

  final String label;
  final String value;
  final bool emphasize;

  @override
  Widget build(BuildContext context) {
    final style = emphasize
        ? Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w700)
        : Theme.of(context).textTheme.bodyMedium;
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: SokoniDimens.space4),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [Text(label, style: style), Text(value, style: style)],
      ),
    );
  }
}
