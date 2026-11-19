import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';

import '../../../core/l10n/gen/app_localizations.dart';
import '../../../core/motion/sokoni_bottom_sheet.dart';
import '../../../core/router/routes.dart';
import '../../../core/theme/colors.dart';
import '../../../core/theme/dimens.dart';
import '../../../core/utils/formatters.dart';
import '../../../data/models/delivery.dart';
import '../../../data/models/user.dart';
import '../../../shared/widgets/report_sheet.dart';
import '../../auth/providers/auth_providers.dart';
import '../../moderation/presentation/moderation_menu.dart';
import '../providers/delivery_providers.dart';
import 'delivery_labels.dart';
import 'widgets/delivery_widgets.dart';

/// Returns on the order screen (docs/delivery/RETURNS.md), for the buyer
/// and the shop: the policy seen at checkout, "Request a return" while the
/// window is open, and then the request with everything each side can do.
/// Only built when the server sent a `return` block, i.e. for users inside
/// the returns launch.
class ReturnSection extends ConsumerWidget {
  const ReturnSection({required this.tracking, required this.isSeller, this.buyer, super.key});

  final DeliveryTracking tracking;
  final bool isSeller;

  /// The order's buyer, on the shop's side, so the shop can block them
  /// from the request.
  final SokoniUser? buyer;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = AppLocalizations.of(context);
    final info = tracking.returnInfo;
    if (info == null) return const SizedBox.shrink();
    final request = info.request;
    if (request != null) {
      return _ReturnCard(orderId: tracking.orderId, request: request, isSeller: isSeller, buyer: buyer);
    }
    if (isSeller) return const SizedBox.shrink();

    final until = info.eligibleUntil;
    return DeliveryCard(
      title: l10n.returnSectionTitle,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text(returnPolicyLabel(l10n, info.policy.code), key: const Key('order-return-policy')),
          if (info.policy.notes != null) Text(info.policy.notes!, style: Theme.of(context).textTheme.bodySmall),
          if (info.canRequest && until != null) ...[
            const SizedBox(height: SokoniDimens.space4),
            Text(l10n.returnRequestUntil(DateFormat('d MMM, HH:mm').format(until)), style: Theme.of(context).textTheme.bodySmall),
            const SizedBox(height: SokoniDimens.space12),
            OutlinedButton.icon(
              key: const Key('request-return'),
              onPressed: () async {
                if (await ensureReturnsTermsAccepted(context, ref) && context.mounted) {
                  await context.push(SokoniRoutes.requestReturn(tracking.orderId));
                  ref.invalidate(deliveryTrackingProvider(tracking.orderId));
                }
              },
              icon: const Icon(Icons.assignment_return_outlined),
              label: Text(l10n.returnRequestButton),
            ),
          ],
        ],
      ),
    );
  }
}

/// The Return Policy section of the Terms (1.2), agreed to once before a
/// user's first return. Stored on its own (not as the Terms version), so no
/// earlier build of the app is ever asked to agree to anything again.
Future<bool> ensureReturnsTermsAccepted(BuildContext context, WidgetRef ref) async {
  final features = await ref.read(featuresProvider.future);
  if (features.returnsTermsAccepted || features.returnsTermsVersion == null || !context.mounted) {
    return features.returnsTermsAccepted || features.returnsTermsVersion == null;
  }
  final agreed = await showSokoniBottomSheet<bool>(
    context: context,
    initialChildSize: 0.8,
    builder: (context) => _ReturnsTermsSheet(version: features.returnsTermsVersion!),
  );
  return agreed ?? false;
}

class _ReturnsTermsSheet extends ConsumerStatefulWidget {
  const _ReturnsTermsSheet({required this.version});

  final String version;

  @override
  ConsumerState<_ReturnsTermsSheet> createState() => _ReturnsTermsSheetState();
}

class _ReturnsTermsSheetState extends ConsumerState<_ReturnsTermsSheet> {
  bool _sending = false;
  String? _error;

  Future<void> _agree() async {
    setState(() {
      _sending = true;
      _error = null;
    });
    try {
      await ref.read(deliveryRepositoryProvider).acceptReturnsTerms(widget.version);
      ref.invalidate(featuresProvider);
      if (mounted) Navigator.of(context).pop(true);
    } catch (e) {
      if (mounted) setState(() => _error = deliveryErrorMessage(AppLocalizations.of(context), e));
    } finally {
      if (mounted) setState(() => _sending = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    final theme = Theme.of(context);
    return Padding(
      padding: const EdgeInsets.all(SokoniDimens.space16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text(l10n.returnsTermsTitle(widget.version), style: theme.textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w700)),
          const SizedBox(height: SokoniDimens.space12),
          Text(l10n.returnsTermsBody),
          if (_error != null) ...[
            const SizedBox(height: SokoniDimens.space8),
            Text(_error!, style: const TextStyle(color: SokoniColors.danger)),
          ],
          const SizedBox(height: SokoniDimens.space16),
          FilledButton(
            key: const Key('agree-returns-terms'),
            onPressed: _sending ? null : _agree,
            child: Text(l10n.returnsTermsAgree),
          ),
        ],
      ),
    );
  }
}

class _ReturnCard extends ConsumerStatefulWidget {
  const _ReturnCard({required this.orderId, required this.request, required this.isSeller, this.buyer});

  final int orderId;
  final ReturnRequestInfo request;
  final bool isSeller;
  final SokoniUser? buyer;

  @override
  ConsumerState<_ReturnCard> createState() => _ReturnCardState();
}

class _ReturnCardState extends ConsumerState<_ReturnCard> {
  bool _busy = false;

  Future<void> _act(String action, [Map<String, dynamic>? body]) async {
    final l10n = AppLocalizations.of(context);
    setState(() => _busy = true);
    try {
      await ref.read(deliveryTrackingProvider(widget.orderId).notifier).returnAction(widget.request.id, action, body);
    } catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(deliveryErrorMessage(l10n, e))));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  /// A short text from the user: [required] blocks an empty answer.
  Future<String?> _askText({required String title, required String label, required bool required, required String confirm}) {
    return showDialog<String>(
      context: context,
      builder: (context) => _TextPromptDialog(title: title, label: label, required: required, confirm: confirm),
    );
  }

  Future<bool> _confirm(String text) async {
    final l10n = AppLocalizations.of(context);
    final ok = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        content: Text(text),
        actions: [
          TextButton(onPressed: () => Navigator.of(context).pop(false), child: Text(l10n.commonCancel)),
          FilledButton(onPressed: () => Navigator.of(context).pop(true), child: Text(l10n.deliveryConfirmYes)),
        ],
      ),
    );
    return ok ?? false;
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    final theme = Theme.of(context);
    final r = widget.request;
    final a = r.actions;
    final date = DateFormat('d MMM, HH:mm');
    final ride = r.ride;

    return DeliveryCard(
      title: l10n.returnSectionTitle,
      highlight: (widget.isSeller && a.canAccept) || a.canPay || a.canBookRide,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            children: [
              Expanded(child: Text(returnReasonLabel(l10n, r.reason), style: theme.textTheme.titleSmall)),
              Chip(key: const Key('return-status'), label: Text(returnStatusLabel(l10n, r.status))),
              // Apple Guideline 1.2: the shop can report the request and
              // block the buyer from the request itself. The buyer's own
              // block can't make a return disappear: the request then goes
              // to Sokoni support (docs/delivery/RETURNS.md).
              if (a.canReport && widget.buyer != null)
                KeyedSubtree(
                  key: const Key('report-return'),
                  child: ModerationMenuButton(
                    target: ModerationTarget(
                      reportType: 'return_request',
                      reportId: r.id,
                      reportLabel: l10n.moderationReportReturn,
                      authorName: widget.buyer!.name,
                      authorUserId: widget.buyer!.id,
                    ),
                  ),
                )
              else if (a.canReport)
                IconButton(
                  key: const Key('report-return'),
                  tooltip: l10n.returnReport,
                  icon: const Icon(Icons.flag_outlined),
                  onPressed: () => showReportSheet(context, ref, reportableType: 'return_request', reportableId: r.id),
                ),
            ],
          ),
          if (r.comment != null) Padding(padding: const EdgeInsets.only(top: SokoniDimens.space4), child: Text(r.comment!)),
          if (r.contentHidden && widget.isSeller) Text(l10n.returnContentHidden, style: theme.textTheme.bodySmall),
          if (r.photos.isNotEmpty) ...[
            const SizedBox(height: SokoniDimens.space8),
            SizedBox(
              height: 72,
              child: ListView.separated(
                scrollDirection: Axis.horizontal,
                itemCount: r.photos.length,
                separatorBuilder: (_, _) => const SizedBox(width: SokoniDimens.space8),
                itemBuilder: (context, i) => GestureDetector(
                  onTap: () => showDialog<void>(
                    context: context,
                    builder: (_) => Dialog(child: InteractiveViewer(child: Image.network(r.photos[i]))),
                  ),
                  child: ClipRRect(
                    borderRadius: BorderRadius.circular(12),
                    child: Image.network(
                      r.photos[i],
                      width: 72,
                      height: 72,
                      fit: BoxFit.cover,
                      semanticLabel: l10n.returnPhotoLabel(i + 1),
                      errorBuilder: (_, _, _) => const SizedBox(width: 72, height: 72, child: Icon(Icons.broken_image_outlined)),
                    ),
                  ),
                ),
              ),
            ),
          ],
          const SizedBox(height: SokoniDimens.space8),
          if (r.handledBySupport) Text(l10n.returnHandledBySupport, style: theme.textTheme.bodySmall?.copyWith(fontWeight: FontWeight.w600)),
          if (r.status == 'requested' && r.sellerDeadline != null && !r.handledBySupport)
            Text(
              widget.isSeller ? l10n.returnSellerAnswerBy(date.format(r.sellerDeadline!)) : l10n.returnShopAnswersBy(date.format(r.sellerDeadline!)),
              style: theme.textTheme.bodySmall,
            ),
          if (r.decisionNote != null && r.status == 'rejected') Text(l10n.returnShopReason(r.decisionNote!)),
          if (r.disputeComment != null) Text(l10n.returnYourDispute(r.disputeComment!), style: theme.textTheme.bodySmall),
          if (r.resolutionNote != null) Text(l10n.returnSokoniDecision(r.resolutionNote!), style: theme.textTheme.bodySmall),
          if (r.fee != null && {'accepted', 'return_in_transit', 'received'}.contains(r.status))
            Text(
              r.payer == 'seller'
                  ? l10n.returnRidePaidByShop(SokoniFormat.tzs(r.fee!))
                  : (r.feeStatus == 'paid' ? l10n.returnRidePaid(SokoniFormat.tzs(r.fee!)) : l10n.returnRideYouPay(SokoniFormat.tzs(r.fee!))),
              style: theme.textTheme.bodySmall,
            ),
          if (ride?.status == 'unavailable') Text(l10n.returnRideUnavailable, style: theme.textTheme.bodySmall),
          if (ride != null && {'no_driver_found', 'failed'}.contains(ride.status) && r.status == 'accepted')
            Text(l10n.returnRideFailed, style: theme.textTheme.bodySmall?.copyWith(color: SokoniColors.danger)),
          if (ride?.driver != null) ...[
            const SizedBox(height: SokoniDimens.space8),
            BoltDriverCard(driver: ride!.driver!, trackingUrl: ride.trackingUrl, title: l10n.returnRideDriverTitle),
          ],
          if (a.canPay) _ReturnPayForm(orderId: widget.orderId, request: r),
          // Buyer actions.
          if (a.canBookRide)
            FilledButton.icon(
              key: const Key('book-return-ride'),
              onPressed: _busy ? null : () async => await _confirm(l10n.returnBookConfirm) ? _act('book') : null,
              icon: const Icon(Icons.local_shipping_outlined),
              label: Text(l10n.returnBookRide),
            ),
          if (a.canDispute)
            OutlinedButton(
              key: const Key('dispute-return'),
              onPressed: _busy
                  ? null
                  : () async {
                      final comment = await _askText(title: l10n.returnDisputeTitle, label: l10n.returnDisputeLabel, required: true, confirm: l10n.returnDispute);
                      if (comment != null) await _act('dispute', {'comment': comment});
                    },
              child: Text(l10n.returnDispute),
            ),
          if (a.canWithdraw && !widget.isSeller)
            TextButton(
              onPressed: _busy ? null : () async => await _confirm(l10n.returnWithdrawConfirm) ? _act('withdraw') : null,
              child: Text(l10n.returnWithdraw),
            ),
          // Shop actions.
          if (a.canAccept)
            FilledButton(
              key: const Key('accept-return'),
              onPressed: _busy ? null : () async => await _confirm(l10n.returnAcceptConfirm) ? _act('accept') : null,
              child: Text(l10n.returnAccept),
            ),
          if (a.canReject)
            OutlinedButton(
              key: const Key('reject-return'),
              onPressed: _busy
                  ? null
                  : () async {
                      final reason = await _askText(title: l10n.returnRejectTitle, label: l10n.returnRejectLabel, required: true, confirm: l10n.returnReject);
                      if (reason != null) await _act('reject', {'reason': reason});
                    },
              child: Text(l10n.returnReject),
            ),
          if (a.canConfirmRefund)
            FilledButton.icon(
              key: const Key('confirm-refund'),
              onPressed: _busy
                  ? null
                  : () async {
                      final note = await _askText(title: l10n.returnRefundTitle, label: l10n.returnRefundLabel, required: false, confirm: l10n.returnRefundDone);
                      if (note != null) await _act('refunded', {if (note.isNotEmpty) 'note': note});
                    },
              icon: const Icon(Icons.check_circle_outline),
              label: Text(l10n.returnRefundDone),
            ),
        ],
      ),
    );
  }
}

/// Asks for one short text (a reason, a note). Owns its controller, so it
/// outlives the dialog's closing animation.
class _TextPromptDialog extends StatefulWidget {
  const _TextPromptDialog({required this.title, required this.label, required this.required, required this.confirm});

  final String title;
  final String label;
  final bool required;
  final String confirm;

  @override
  State<_TextPromptDialog> createState() => _TextPromptDialogState();
}

class _TextPromptDialogState extends State<_TextPromptDialog> {
  final _controller = TextEditingController();

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final tooShort = widget.required && _controller.text.trim().length < 3;
    return AlertDialog(
      title: Text(widget.title),
      content: TextField(
        controller: _controller,
        maxLength: 500,
        maxLines: 3,
        autofocus: true,
        decoration: InputDecoration(labelText: widget.label),
        onChanged: (_) => setState(() {}),
      ),
      actions: [
        TextButton(onPressed: () => Navigator.of(context).pop(), child: Text(AppLocalizations.of(context).commonCancel)),
        FilledButton(
          onPressed: tooShort ? null : () => Navigator.of(context).pop(_controller.text.trim()),
          child: Text(widget.confirm),
        ),
      ],
    );
  }
}

/// Paying the Bolt ride back for a "changed my mind" return: the same Pay
/// now as the delivery fee, for exactly the server's amount.
class _ReturnPayForm extends ConsumerStatefulWidget {
  const _ReturnPayForm({required this.orderId, required this.request});

  final int orderId;
  final ReturnRequestInfo request;

  @override
  ConsumerState<_ReturnPayForm> createState() => _ReturnPayFormState();
}

class _ReturnPayFormState extends ConsumerState<_ReturnPayForm> {
  final _phone = TextEditingController();
  String _channel = 'mpesa';
  bool _sending = false;
  bool _prefilled = false;
  String? _error;
  String? _pendingKey;

  @override
  void dispose() {
    _phone.dispose();
    super.dispose();
  }

  Future<void> _pay() async {
    final l10n = AppLocalizations.of(context);
    final msisdn = SokoniFormat.phoneToE164(_phone.text);
    if (msisdn == null) {
      setState(() => _error = l10n.checkoutPhoneInvalid);
      return;
    }
    setState(() {
      _sending = true;
      _error = null;
    });
    _pendingKey ??= newIdempotencyKey();
    try {
      await ref.read(deliveryTrackingProvider(widget.orderId).notifier).returnAction(widget.request.id, 'pay', {
        'channel': _channel,
        'msisdn': msisdn,
        'idempotency_key': _pendingKey,
      });
      _pendingKey = null;
    } on DeliveryActionError catch (e) {
      _pendingKey = null;
      if (mounted) setState(() => _error = e.message);
    } catch (e) {
      if (mounted) setState(() => _error = '$e');
    } finally {
      if (mounted) setState(() => _sending = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    if (!_prefilled) {
      final phone = ref.watch(currentUserProvider).value?.phone;
      if (phone != null) {
        _prefilled = true;
        _phone.text = SokoniFormat.phoneLocal(phone);
      }
    }
    final attempt = widget.request.paymentAttempt;
    if (attempt?.isPending ?? false) {
      return Padding(
        padding: const EdgeInsets.symmetric(vertical: SokoniDimens.space8),
        child: Text(l10n.deliveryPayPending(SokoniFormat.tzs(attempt!.amount))),
      );
    }
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        const SizedBox(height: SokoniDimens.space8),
        if (attempt != null && attempt.isFailed) Text(paymentFailureLabel(l10n, attempt.failureReason), style: const TextStyle(color: SokoniColors.danger)),
        Wrap(
          spacing: SokoniDimens.space8,
          children: [
            for (final channel in const ['mpesa', 'airtel', 'mixx', 'halopesa'])
              ChoiceChip(label: Text(paymentChannelLabel(l10n, channel)), selected: _channel == channel, onSelected: (_) => setState(() => _channel = channel)),
          ],
        ),
        TextField(
          controller: _phone,
          keyboardType: TextInputType.phone,
          decoration: InputDecoration(labelText: l10n.deliveryPayPhone, hintText: '0754 123 456'),
        ),
        if (_error != null) Text(_error!, style: const TextStyle(color: SokoniColors.danger)),
        const SizedBox(height: SokoniDimens.space8),
        FilledButton(
          key: const Key('pay-return-ride'),
          onPressed: _sending ? null : () => unawaited(_pay()),
          child: Text(l10n.deliveryPayNow(SokoniFormat.tzs(widget.request.amountDue))),
        ),
      ],
    );
  }
}
