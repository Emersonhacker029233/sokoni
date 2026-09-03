import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/l10n/gen/app_localizations.dart';
import '../../../core/location/location_service.dart';
import '../../../core/motion/success_checkmark.dart';
import '../../../core/network/api_exception.dart';
import '../../../core/providers.dart';
import '../../../core/router/routes.dart';
import '../../../core/theme/dimens.dart';
import '../../../core/utils/formatters.dart';
import '../../auth/presentation/sign_in_prompt_sheet.dart';
import '../providers/order_providers.dart';

class CheckoutScreen extends ConsumerStatefulWidget {
  const CheckoutScreen({super.key});

  @override
  ConsumerState<CheckoutScreen> createState() => _CheckoutScreenState();
}

class _CheckoutScreenState extends ConsumerState<CheckoutScreen> {
  final _addressController = TextEditingController();
  final _notesController = TextEditingController();
  String _deliveryMethod = 'pickup';
  String _paymentMethod = 'cash_on_delivery';
  double? _lat;
  double? _lng;
  bool _submitting = false;
  String? _error;

  @override
  void dispose() {
    _addressController.dispose();
    _notesController.dispose();
    super.dispose();
  }

  Future<void> _useCurrentLocation() async {
    final result = await ref.read(locationServiceProvider).current();
    if (result is LocationAvailable) {
      setState(() {
        _lat = result.lat;
        _lng = result.lng;
      });
    }
  }

  Future<void> _placeOrder() async {
    final cart = ref.read(cartProvider);
    if (cart.isEmpty) return;
    unawaited(HapticFeedback.mediumImpact());
    if (_deliveryMethod == 'delivery' && _addressController.text.trim().isEmpty) {
      setState(() => _error = AppLocalizations.of(context).checkoutAddress);
      return;
    }
    setState(() {
      _submitting = true;
      _error = null;
    });
    try {
      final order = await ref.read(orderRepositoryProvider).checkout(
        items: [for (final line in cart) (productId: line.product.id, qty: line.qty)],
        deliveryMethod: _deliveryMethod,
        address: _deliveryMethod == 'delivery' ? _addressController.text.trim() : null,
        deliveryLat: _deliveryMethod == 'delivery' ? _lat : null,
        deliveryLng: _deliveryMethod == 'delivery' ? _lng : null,
        notes: _notesController.text.trim().isEmpty ? null : _notesController.text.trim(),
        paymentMethod: _paymentMethod,
      );
      ref.read(cartProvider.notifier).clear();
      if (!mounted) return;
      await showSokoniSuccess(
        context,
        message: AppLocalizations.of(context).checkoutSuccessTitle(order.code),
      );
      if (!mounted) return;
      if (order.conversationId != null) {
        context.pushReplacement(SokoniRoutes.conversation(order.conversationId!));
      } else {
        context.pushReplacement(SokoniRoutes.orderDetail(order.id));
      }
    } on UnauthenticatedException {
      // The token could have been revoked between opening checkout and
      // submitting — rare, since Sanctum tokens here don't expire on a
      // schedule (`config/sanctum.php`), but still a real, if unlikely,
      // path. An invitation to sign in, not a red "please sign in again"
      // error — the cart itself survives untouched either way.
      if (mounted) await showSignInPrompt(context, message: AppLocalizations.of(context).guestPromptOrder);
    } on ApiException catch (e) {
      setState(() => _error = e.message);
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    final cart = ref.watch(cartProvider);
    final subtotal = cart.fold(0, (sum, line) => sum + line.lineTotal);
    final deliveryFee = _deliveryMethod == 'delivery' ? 3000 : 0;

    return Scaffold(
      appBar: AppBar(title: Text(l10n.checkoutTitle)),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(SokoniDimens.space20),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(l10n.checkoutDeliveryMethod, style: Theme.of(context).textTheme.titleSmall),
            const SizedBox(height: SokoniDimens.space8),
            SegmentedButton<String>(
              segments: [
                ButtonSegment(value: 'pickup', label: Text(l10n.checkoutPickup)),
                ButtonSegment(value: 'delivery', label: Text(l10n.checkoutDelivery)),
              ],
              selected: {_deliveryMethod},
              onSelectionChanged: (selection) => setState(() => _deliveryMethod = selection.first),
            ),
            if (_deliveryMethod == 'delivery') ...[
              const SizedBox(height: SokoniDimens.space16),
              TextField(
                controller: _addressController,
                decoration: InputDecoration(labelText: l10n.checkoutAddress),
              ),
              const SizedBox(height: SokoniDimens.space8),
              OutlinedButton.icon(
                onPressed: _useCurrentLocation,
                icon: Icon(_lat == null ? Icons.my_location_outlined : Icons.check_circle_outline_rounded),
                label: Text(l10n.checkoutUseCurrentLocation),
              ),
            ],
            const SizedBox(height: SokoniDimens.space20),
            TextField(
              controller: _notesController,
              maxLines: 3,
              decoration: InputDecoration(labelText: l10n.checkoutNotes),
            ),
            const SizedBox(height: SokoniDimens.space20),
            Text(l10n.checkoutPaymentMethod, style: Theme.of(context).textTheme.titleSmall),
            RadioGroup<String>(
              groupValue: _paymentMethod,
              onChanged: (value) => setState(() => _paymentMethod = value ?? _paymentMethod),
              child: Column(
                children: [
                  RadioListTile<String>(
                    contentPadding: EdgeInsets.zero,
                    value: 'cash_on_delivery',
                    title: Text(l10n.checkoutCashOnDelivery),
                  ),
                  RadioListTile<String>(
                    contentPadding: EdgeInsets.zero,
                    value: 'pay_on_pickup',
                    title: Text(l10n.checkoutPayOnPickup),
                  ),
                ],
              ),
            ),
            const SizedBox(height: SokoniDimens.space20),
            _SummaryRow(label: l10n.cartSubtotal, value: SokoniFormat.tzs(subtotal)),
            _SummaryRow(label: l10n.checkoutDeliveryFee, value: SokoniFormat.tzs(deliveryFee)),
            const Divider(),
            _SummaryRow(
              label: l10n.checkoutTotal,
              value: SokoniFormat.tzs(subtotal + deliveryFee),
              emphasize: true,
            ),
            if (_error != null) ...[
              const SizedBox(height: SokoniDimens.space8),
              Text(_error!, style: const TextStyle(color: Colors.red)),
            ],
            const SizedBox(height: SokoniDimens.space24),
            FilledButton(
              onPressed: (_submitting || cart.isEmpty) ? null : _placeOrder,
              child: _submitting
                  ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2))
                  : Text(l10n.checkoutPlaceOrder),
            ),
          ],
        ),
      ),
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
