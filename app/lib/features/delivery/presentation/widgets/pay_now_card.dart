import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../../core/l10n/gen/app_localizations.dart';
import '../../../../core/theme/colors.dart';
import '../../../../core/theme/dimens.dart';
import '../../../../core/utils/formatters.dart';
import '../../../../data/models/delivery.dart';
import '../../../auth/providers/auth_providers.dart';
import '../../providers/delivery_providers.dart';
import '../delivery_labels.dart';
import 'delivery_widgets.dart';

/// "Pay now" after the shop accepts. Shows the server's breakdown (goods,
/// delivery fee, total), the deadline, and sends the payment prompt for
/// exactly the server's `amount_due`. The app only starts the attempt; the
/// card moves on when the server reports the payment confirmed.
class PayNowCard extends ConsumerStatefulWidget {
  const PayNowCard({required this.tracking, super.key});

  final DeliveryTracking tracking;

  @override
  ConsumerState<PayNowCard> createState() => _PayNowCardState();
}

class _PayNowCardState extends ConsumerState<PayNowCard> {
  final _phone = TextEditingController();
  String? _channel;
  bool _prefilled = false;
  bool _sending = false;
  String? _error;
  Timer? _tick;

  /// Reused if the same tap has to be re-sent (e.g. the connection dropped),
  /// so the server never sends two prompts for one tap.
  String? _pendingKey;

  @override
  void initState() {
    super.initState();
    // Keeps the "pay within N min" line honest without a rebuild from the poll.
    _tick = Timer.periodic(const Duration(seconds: 20), (_) {
      if (mounted) setState(() {});
    });
  }

  @override
  void dispose() {
    _tick?.cancel();
    _phone.dispose();
    super.dispose();
  }

  Future<void> _pay(DeliveryPaymentState payment) async {
    final l10n = AppLocalizations.of(context);
    final msisdn = SokoniFormat.phoneToE164(_phone.text);
    if (msisdn == null) {
      setState(() => _error = l10n.checkoutPhoneInvalid);
      return;
    }
    unawaited(HapticFeedback.mediumImpact());
    setState(() {
      _sending = true;
      _error = null;
    });
    _pendingKey ??= newIdempotencyKey();
    try {
      await ref
          .read(deliveryTrackingProvider(widget.tracking.orderId).notifier)
          .pay(channel: _channel ?? payment.channels.first, msisdn: msisdn, idempotencyKey: _pendingKey!);
      _pendingKey = null;
    } on DeliveryActionError catch (e) {
      _pendingKey = null;
      if (mounted) setState(() => _error = e.message);
    } catch (e) {
      // Network trouble: keep the key so a retry can't create a second prompt.
      if (mounted) setState(() => _error = '$e');
    } finally {
      if (mounted) setState(() => _sending = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    final theme = Theme.of(context);
    final payment = widget.tracking.payment;
    if (payment == null) return const SizedBox.shrink();

    if (!_prefilled) {
      final phone = ref.watch(currentUserProvider).value?.phone;
      if (phone != null) {
        _prefilled = true;
        _phone.text = SokoniFormat.phoneLocal(phone);
      }
    }
    final channels = payment.channels.isEmpty ? const ['mpesa'] : payment.channels;
    _channel ??= channels.first;
    final attempt = payment.attempt;
    final waiting = attempt?.isPending ?? false;
    final minutesLeft = payment.deadline?.difference(DateTime.now()).inMinutes.clamp(0, 999);
    final amount = SokoniFormat.tzs(payment.amountDue);

    return DeliveryCard(
      highlight: true,
      title: l10n.deliveryPayTitle,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          _Line(label: l10n.checkoutGoods, value: SokoniFormat.tzs(payment.goodsTotal), note: l10n.deliveryPayGoodsNote),
          _Line(label: l10n.deliveryFeeLine, value: SokoniFormat.tzs(payment.deliveryFee)),
          const Divider(),
          _Line(label: l10n.checkoutTotal, value: SokoniFormat.tzs(payment.total), bold: true),
          const SizedBox(height: SokoniDimens.space4),
          // Flat delivery fee: the fixed fee and nothing of Bolt's own prices.
          Text(l10n.deliveryPayCoversFee, key: const Key('pay-fixed-fee'), style: theme.textTheme.bodySmall),
          // The return policy copied onto this order at checkout, once more before paying.
          if (widget.tracking.returnInfo?.policy case final policy?) ...[
            const SizedBox(height: SokoniDimens.space8),
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Icon(policy.acceptsReturns ? Icons.assignment_return_outlined : Icons.block_outlined, size: 18),
                const SizedBox(width: SokoniDimens.space4),
                Expanded(
                  child: Text(
                    [l10n.checkoutReturnsLine(returnPolicyLabel(l10n, policy.code)), ?policy.notes].join('\n'),
                    key: const Key('pay-return-policy'),
                    style: theme.textTheme.bodySmall,
                  ),
                ),
              ],
            ),
          ],
          if (minutesLeft != null) ...[
            const SizedBox(height: SokoniDimens.space8),
            Row(
              children: [
                const Icon(Icons.timer_outlined, size: 18),
                const SizedBox(width: SokoniDimens.space4),
                Expanded(child: Text(l10n.deliveryPayDeadline(minutesLeft), style: theme.textTheme.bodySmall?.copyWith(fontWeight: FontWeight.w600))),
              ],
            ),
          ],
          const SizedBox(height: SokoniDimens.space16),
          if (!payment.onlineAvailable)
            Text(l10n.deliveryPayUnavailable, style: theme.textTheme.bodyMedium?.copyWith(color: SokoniColors.danger))
          else if (waiting)
            _Waiting(attempt: attempt!)
          else ...[
            if (attempt != null && attempt.isFailed) ...[
              Text(paymentFailureLabel(l10n, attempt.failureReason), style: theme.textTheme.bodyMedium?.copyWith(color: SokoniColors.danger)),
              const SizedBox(height: SokoniDimens.space8),
            ],
            Text(l10n.deliveryPayChannel, style: theme.textTheme.labelLarge),
            const SizedBox(height: SokoniDimens.space8),
            Wrap(
              spacing: SokoniDimens.space8,
              runSpacing: SokoniDimens.space8,
              children: [
                for (final channel in channels)
                  ChoiceChip(
                    label: Text(paymentChannelLabel(l10n, channel)),
                    selected: _channel == channel,
                    onSelected: (_) => setState(() => _channel = channel),
                  ),
              ],
            ),
            const SizedBox(height: SokoniDimens.space12),
            TextField(
              key: const Key('pay-phone'),
              controller: _phone,
              keyboardType: TextInputType.phone,
              decoration: InputDecoration(labelText: l10n.deliveryPayPhone, hintText: '0754 123 456'),
            ),
            if (_error != null) ...[
              const SizedBox(height: SokoniDimens.space8),
              Text(_error!, style: const TextStyle(color: SokoniColors.danger)),
            ],
            const SizedBox(height: SokoniDimens.space12),
            SizedBox(
              width: double.infinity,
              child: FilledButton(
                key: const Key('pay-now'),
                onPressed: _sending || !widget.tracking.actions.canPay ? null : () => _pay(payment),
                child: _sending
                    ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2))
                    : Text(attempt != null && attempt.isFailed ? '${l10n.deliveryPayTryAgain} · $amount' : l10n.deliveryPayNow(amount)),
              ),
            ),
          ],
          if (payment.sandbox) ...[
            const SizedBox(height: SokoniDimens.space12),
            Text(l10n.deliveryPaySandbox, style: theme.textTheme.bodySmall?.copyWith(fontStyle: FontStyle.italic)),
          ],
        ],
      ),
    );
  }
}

class _Waiting extends StatelessWidget {
  const _Waiting({required this.attempt});

  final PaymentAttempt attempt;

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    final theme = Theme.of(context);
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          children: [
            const SizedBox(width: 20, height: 20, child: CircularProgressIndicator(strokeWidth: 2)),
            const SizedBox(width: SokoniDimens.space12),
            Expanded(child: Text(l10n.deliveryPayPending(SokoniFormat.tzs(attempt.amount)), style: theme.textTheme.bodyMedium)),
          ],
        ),
        if (attempt.msisdn != null) ...[
          const SizedBox(height: SokoniDimens.space8),
          Text(
            [l10n.deliveryPaySentTo(attempt.msisdn!), if (attempt.channel != null) paymentChannelLabel(l10n, attempt.channel!)].join(' · '),
            style: theme.textTheme.bodySmall,
          ),
        ],
        const SizedBox(height: SokoniDimens.space8),
        Text(l10n.deliveryPayPendingHint, key: const Key('pay-pending-hint'), style: theme.textTheme.bodySmall),
      ],
    );
  }
}

class _Line extends StatelessWidget {
  const _Line({required this.label, required this.value, this.note, this.bold = false});

  final String label;
  final String value;
  final String? note;
  final bool bold;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final style = bold ? theme.textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w700) : theme.textTheme.bodyMedium;
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 2),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(label, style: style),
                if (note != null) Text(note!, style: theme.textTheme.bodySmall),
              ],
            ),
          ),
          Text(value, style: style),
        ],
      ),
    );
  }
}
