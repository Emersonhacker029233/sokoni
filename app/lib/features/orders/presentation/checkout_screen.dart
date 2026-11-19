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
import '../../../core/theme/colors.dart';
import '../../../core/theme/dimens.dart';
import '../../../core/utils/formatters.dart';
import '../../../data/models/delivery.dart';
import '../../auth/presentation/sign_in_prompt_sheet.dart';
import '../../auth/providers/auth_providers.dart';
import '../../delivery/presentation/delivery_labels.dart';
import '../../delivery/presentation/delivery_location_picker_screen.dart';
import '../../delivery/providers/delivery_providers.dart';
import '../providers/order_providers.dart';

/// Checkout. Two flows share this screen:
///
///  - Sokoni 1.0 (unchanged): pickup or seller-arranged delivery with a
///    free-text address and the flat TSh 3,000 fee.
///  - Bolt delivery: when this build is 1.1+ and the server says delivery
///    is on for this user (GET /features), "Delivery" means a Bolt driver. The buyer must
///    drop a map pin (OpenStreetMap), confirm a readable address and give a
///    phone number, and the fee shown is the server's quote for the
///    distance from the shop. Payment happens after the shop accepts.
class CheckoutScreen extends ConsumerStatefulWidget {
  const CheckoutScreen({super.key});

  @override
  ConsumerState<CheckoutScreen> createState() => _CheckoutScreenState();
}

class _CheckoutScreenState extends ConsumerState<CheckoutScreen> {
  final _addressController = TextEditingController();
  final _notesController = TextEditingController();
  final _phoneController = TextEditingController();
  String _deliveryMethod = 'pickup';
  String _paymentMethod = 'cash_on_delivery';
  double? _lat;
  double? _lng;
  PickedDeliveryLocation? _dropoff;
  bool _phonePrefilled = false;

  /// This shop isn't offering Bolt delivery (the quote said `not_available`):
  /// delivery goes back to the ordinary seller-arranged kind.
  bool _boltNotOffered = false;
  bool _submitting = false;
  String? _error;

  @override
  void dispose() {
    _addressController.dispose();
    _notesController.dispose();
    _phoneController.dispose();
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

  Future<void> _pickDropoff() async {
    final picked = await context.push<PickedDeliveryLocation>(SokoniRoutes.deliveryLocation, extra: _dropoff);
    if (picked != null && mounted) {
      setState(() {
        _dropoff = picked;
        _error = null;
      });
    }
  }

  /// What still blocks a Sokoni-delivery order, or null when it can go.
  String? _dispatchProblem(AppLocalizations l10n, AsyncValue<DeliveryQuote>? quote) {
    if (_dropoff == null) return l10n.checkoutLocationRequired;
    if (_dropoff!.address.trim().isEmpty) return l10n.checkoutAddressRequired;
    if (SokoniFormat.phoneToE164(_phoneController.text) == null) return l10n.checkoutPhoneInvalid;
    if (quote == null || !quote.hasValue) return quote?.hasError ?? false ? _quoteError(l10n, quote!.error!) : l10n.checkoutQuoteLoading;
    return null;
  }

  Future<void> _placeOrder({DeliveryConfig? dispatch, AsyncValue<DeliveryQuote>? quote}) async {
    final cart = ref.read(cartProvider);
    if (cart.isEmpty) return;
    final l10n = AppLocalizations.of(context);
    unawaited(HapticFeedback.mediumImpact());
    final useDispatch = _deliveryMethod == 'delivery' && dispatch != null;
    if (useDispatch) {
      final problem = _dispatchProblem(l10n, quote);
      if (problem != null) {
        setState(() => _error = problem);
        return;
      }
    } else if (_deliveryMethod == 'delivery' && _addressController.text.trim().isEmpty) {
      setState(() => _error = l10n.checkoutAddress);
      return;
    }
    setState(() {
      _submitting = true;
      _error = null;
    });
    try {
      final repo = ref.read(orderRepositoryProvider);
      final items = [for (final line in cart) (productId: line.product.id, qty: line.qty)];
      final notes = _notesController.text.trim().isEmpty ? null : _notesController.text.trim();
      final order = useDispatch
          ? await repo.checkout(
              items: items,
              deliveryMethod: 'delivery',
              fulfilment: 'dispatch',
              address: _dropoff!.address.trim(),
              deliveryLat: _dropoff!.lat,
              deliveryLng: _dropoff!.lng,
              deliveryPhone: SokoniFormat.phoneToE164(_phoneController.text),
              notes: notes,
              // The order's own payment method covers the goods, which the
              // buyer pays to the shop directly; the delivery fee is paid
              // separately after acceptance (docs/delivery/DECISIONS.md).
              paymentMethod: 'cash_on_delivery',
            )
          : await repo.checkout(
              items: items,
              deliveryMethod: _deliveryMethod,
              address: _deliveryMethod == 'delivery' ? _addressController.text.trim() : null,
              deliveryLat: _deliveryMethod == 'delivery' ? _lat : null,
              deliveryLng: _deliveryMethod == 'delivery' ? _lng : null,
              notes: notes,
              paymentMethod: _paymentMethod,
            );
      ref.read(cartProvider.notifier).clear();
      if (!mounted) return;
      await showSokoniSuccess(context, message: AppLocalizations.of(context).checkoutSuccessTitle(order.code));
      if (!mounted) return;
      // A Sokoni-delivery order is followed on its order screen (pay, rider,
      // PIN); a 1.0-style order still opens the chat with the shop.
      if (!useDispatch && order.conversationId != null) {
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
    } on ValidationException catch (e) {
      setState(() => _error = e.fieldError('delivery_lat') ?? e.fieldError('fulfilment') ?? e.fieldError('delivery_phone') ?? e.message);
    } on ApiException catch (e) {
      setState(() => _error = e.message);
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  String _quoteError(AppLocalizations l10n, Object error) {
    if (error is DeliveryActionError) {
      return error.code == 'shop_has_no_location' ? l10n.checkoutShopNoLocation : error.message;
    }
    return l10n.checkoutQuoteFailed;
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    final cart = ref.watch(cartProvider);
    final subtotal = cart.fold(0, (sum, line) => sum + line.lineTotal);
    final availability = ref.watch(deliveryAvailabilityProvider);
    final dispatch = availability.value;
    // Bolt delivery, unless this shop isn't offering it (outside the test while BOLT_TEST_PHONES is set).
    final dispatchMode = _deliveryMethod == 'delivery' && dispatch != null && !_boltNotOffered;
    final checkingDelivery = _deliveryMethod == 'delivery' && availability.isLoading && !availability.hasValue;
    final deliveryCheckFailed = _deliveryMethod == 'delivery' && availability.hasError && !availability.hasValue;

    if (dispatchMode && !_phonePrefilled) {
      final phone = ref.watch(currentUserProvider).value?.phone;
      if (phone != null) {
        _phonePrefilled = true;
        _phoneController.text = SokoniFormat.phoneLocal(phone);
      }
    }

    final sellerId = ref.read(cartProvider.notifier).sellerId;
    final quote = dispatchMode && _dropoff != null && sellerId != null
        ? ref.watch(deliveryQuoteProvider((sellerId: sellerId, lat: _dropoff!.lat, lng: _dropoff!.lng)))
        : null;

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
              onSelectionChanged: (selection) => setState(() {
                _deliveryMethod = selection.first;
                _error = null;
              }),
            ),
            if (checkingDelivery) ...[
              const SizedBox(height: SokoniDimens.space16),
              const LinearProgressIndicator(),
            ] else if (deliveryCheckFailed) ...[
              const SizedBox(height: SokoniDimens.space16),
              _InfoBox(
                icon: Icons.wifi_off_rounded,
                text: l10n.checkoutDeliveryOptionsFailed,
                action: TextButton(onPressed: () => ref.invalidate(deliveryAvailabilityProvider), child: Text(l10n.commonRetry)),
              ),
            ] else if (dispatchMode)
              _DispatchFields(
                dropoff: _dropoff,
                onPick: _pickDropoff,
                phoneController: _phoneController,
                notesController: _notesController,
                config: dispatch,
                onPhoneChanged: () => setState(() => _error = null),
              )
            else if (_deliveryMethod == 'delivery') ...[
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
            if (!dispatchMode) ...[
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
            ],
            const SizedBox(height: SokoniDimens.space20),
            if (dispatchMode)
              _DispatchSummary(
                subtotal: subtotal,
                quote: quote,
                quoteError: quote?.error == null ? null : _quoteError(l10n, quote!.error!),
                onUsePickup: () => setState(() => _deliveryMethod = 'pickup'),
                onUseSellerDelivery: quote?.error is DeliveryActionError && (quote!.error! as DeliveryActionError).code == 'not_available'
                    ? () => setState(() {
                        _boltNotOffered = true;
                        _error = null;
                      })
                    : null,
              )
            else ...[
              _SummaryRow(label: l10n.cartSubtotal, value: SokoniFormat.tzs(subtotal)),
              _SummaryRow(label: l10n.checkoutDeliveryFee, value: SokoniFormat.tzs(_deliveryMethod == 'delivery' ? 3000 : 0)),
              const Divider(),
              _SummaryRow(
                label: l10n.checkoutTotal,
                value: SokoniFormat.tzs(subtotal + (_deliveryMethod == 'delivery' ? 3000 : 0)),
                emphasize: true,
              ),
            ],
            if (_error != null) ...[
              const SizedBox(height: SokoniDimens.space8),
              Text(_error!, style: const TextStyle(color: SokoniColors.danger)),
            ],
            const SizedBox(height: SokoniDimens.space24),
            FilledButton(
              onPressed: (_submitting || cart.isEmpty || checkingDelivery || deliveryCheckFailed)
                  ? null
                  : () => _placeOrder(dispatch: dispatchMode ? dispatch : null, quote: quote),
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

/// Sokoni 1.1 delivery inputs: map location (required), the rider's phone
/// (required) and notes, plus how the money is paid.
class _DispatchFields extends StatelessWidget {
  const _DispatchFields({
    required this.dropoff,
    required this.onPick,
    required this.phoneController,
    required this.notesController,
    required this.config,
    required this.onPhoneChanged,
  });

  final PickedDeliveryLocation? dropoff;
  final VoidCallback onPick;
  final TextEditingController phoneController;
  final TextEditingController notesController;
  final DeliveryConfig config;
  final VoidCallback onPhoneChanged;

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    final theme = Theme.of(context);
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const SizedBox(height: SokoniDimens.space12),
        Row(
          children: [
            const Icon(Icons.local_shipping_outlined, size: 18),
            const SizedBox(width: SokoniDimens.space8),
            Expanded(child: Text(l10n.checkoutDeliveryByBolt, style: theme.textTheme.bodySmall)),
          ],
        ),
        const SizedBox(height: SokoniDimens.space16),
        Text(l10n.checkoutDeliverTo, style: theme.textTheme.titleSmall),
        const SizedBox(height: SokoniDimens.space8),
        if (dropoff == null)
          OutlinedButton.icon(
            key: const Key('checkout-choose-location'),
            onPressed: onPick,
            icon: const Icon(Icons.add_location_alt_outlined),
            label: Text(l10n.checkoutChooseLocation),
          )
        else
          Container(
            padding: const EdgeInsets.all(SokoniDimens.space12),
            decoration: BoxDecoration(
              border: Border.all(color: theme.dividerColor),
              borderRadius: BorderRadius.circular(12),
            ),
            child: Row(
              children: [
                const Icon(Icons.location_on_rounded),
                const SizedBox(width: SokoniDimens.space8),
                Expanded(child: Text(dropoff!.address, maxLines: 3, overflow: TextOverflow.ellipsis)),
                TextButton(onPressed: onPick, child: Text(l10n.checkoutChangeLocation)),
              ],
            ),
          ),
        const SizedBox(height: SokoniDimens.space16),
        TextField(
          key: const Key('checkout-rider-phone'),
          controller: phoneController,
          keyboardType: TextInputType.phone,
          onChanged: (_) => onPhoneChanged(),
          decoration: InputDecoration(labelText: l10n.checkoutRiderPhone, hintText: '0754 123 456'),
        ),
        const SizedBox(height: SokoniDimens.space16),
        TextField(
          controller: notesController,
          maxLines: 2,
          decoration: InputDecoration(labelText: l10n.checkoutRiderNotes),
        ),
        const SizedBox(height: SokoniDimens.space20),
        Text(l10n.checkoutHowYouPay, style: theme.textTheme.titleSmall),
        const SizedBox(height: SokoniDimens.space8),
        _InfoBox(icon: Icons.payments_outlined, text: l10n.checkoutBoltFeePaidInApp),
        const SizedBox(height: SokoniDimens.space8),
        _InfoBox(icon: Icons.storefront_outlined, text: l10n.checkoutGoodsPaidToShop),
      ],
    );
  }
}

/// Goods, the server's delivery quote, and their sum. Every number here is
/// either a product price or the server's quote; the binding fee is fixed
/// when the shop accepts.
class _DispatchSummary extends StatelessWidget {
  const _DispatchSummary({
    required this.subtotal,
    required this.quote,
    required this.quoteError,
    required this.onUsePickup,
    this.onUseSellerDelivery,
  });

  final int subtotal;
  final AsyncValue<DeliveryQuote>? quote;
  final String? quoteError;
  final VoidCallback onUsePickup;

  /// Set when this shop isn't offering Bolt delivery: switch to the ordinary kind.
  final VoidCallback? onUseSellerDelivery;

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    final theme = Theme.of(context);
    final value = quote?.value;
    final feeLabel = value == null ? l10n.checkoutDeliveryFee : l10n.checkoutDeliveryFeeKm(value.distanceKm.toStringAsFixed(1));
    final feeText = switch (quote) {
      null => l10n.checkoutDeliveryFeeChooseLocation,
      AsyncValue(hasValue: true) => SokoniFormat.tzs(value!.fee),
      AsyncValue(hasError: true) => '—',
      _ => '…',
    };
    final returns = value?.returnPolicy;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        _SummaryRow(label: l10n.checkoutGoods, value: SokoniFormat.tzs(subtotal)),
        _SummaryRow(label: feeLabel, value: feeText),
        if (value != null) _BoltEstimate(quote: value),
        if (value?.etaSeconds != null) Text(l10n.checkoutBoltEta((value!.etaSeconds! / 60).ceil()), style: theme.textTheme.bodySmall),
        if (quote != null && quote!.isLoading && !quote!.hasValue) const LinearProgressIndicator(minHeight: 2),
        const Divider(),
        _SummaryRow(
          label: l10n.checkoutEstimatedTotal,
          value: value == null ? SokoniFormat.tzs(subtotal) : SokoniFormat.tzs(subtotal + value.fee),
          emphasize: true,
        ),
        const SizedBox(height: SokoniDimens.space4),
        Text(l10n.checkoutFeeConfirmedOnAccept, style: theme.textTheme.bodySmall),
        // Returns (docs/delivery/RETURNS.md): shown before the buyer commits.
        if (returns != null) ...[
          const SizedBox(height: SokoniDimens.space12),
          _InfoBox(
            icon: returns.acceptsReturns ? Icons.assignment_return_outlined : Icons.block_outlined,
            text: [l10n.checkoutReturnsLine(returnPolicyLabel(l10n, returns.code)), ?returns.notes].join('\n'),
          ),
        ],
        if (quoteError != null) ...[
          const SizedBox(height: SokoniDimens.space12),
          _InfoBox(
            icon: Icons.info_outline_rounded,
            text: quoteError!,
            action: onUseSellerDelivery != null
                ? TextButton(onPressed: onUseSellerDelivery, child: Text(l10n.checkoutUseSellerDelivery))
                : TextButton(onPressed: onUsePickup, child: Text(l10n.checkoutUsePickup)),
          ),
        ],
      ],
    );
  }
}

/// Bolt's estimate and what the buyer will actually pay for delivery: the
/// top of the range, with the honest note that Bolt's final price for the
/// trip can come in lower. Every number is the server's quote.
class _BoltEstimate extends StatelessWidget {
  const _BoltEstimate({required this.quote});

  final DeliveryQuote quote;

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    final theme = Theme.of(context);
    final dark = theme.brightness == Brightness.dark;
    return Container(
      key: const Key('checkout-bolt-estimate'),
      width: double.infinity,
      margin: const EdgeInsets.symmetric(vertical: SokoniDimens.space8),
      padding: const EdgeInsets.all(SokoniDimens.space12),
      decoration: BoxDecoration(
        border: Border.all(color: dark ? SokoniColors.darkOutline : SokoniColors.outline),
        borderRadius: BorderRadius.circular(SokoniDimens.radiusField),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          if (quote.min != null && quote.max != null)
            Text(
              l10n.checkoutBoltRange(SokoniFormat.tzs(quote.min!.round()), SokoniFormat.tzs(quote.max!.ceil())),
              key: const Key('checkout-bolt-range'),
              style: theme.textTheme.bodyMedium,
            ),
          Text(
            l10n.checkoutBoltYouPay(SokoniFormat.tzs(quote.fee)),
            key: const Key('checkout-bolt-you-pay'),
            style: theme.textTheme.bodyMedium?.copyWith(fontWeight: FontWeight.w700),
          ),
          const SizedBox(height: SokoniDimens.space4),
          Text(l10n.checkoutBoltFinalCanBeLower, style: theme.textTheme.bodySmall),
        ],
      ),
    );
  }
}

class _InfoBox extends StatelessWidget {
  const _InfoBox({required this.icon, required this.text, this.action});

  final IconData icon;
  final String text;
  final Widget? action;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(SokoniDimens.space12),
      decoration: BoxDecoration(
        color: theme.brightness == Brightness.dark ? SokoniColors.darkSurfaceAlt : SokoniColors.surfaceAlt,
        borderRadius: BorderRadius.circular(12),
      ),
      child: Row(
        children: [
          Icon(icon, size: 20),
          const SizedBox(width: SokoniDimens.space8),
          Expanded(child: Text(text, style: theme.textTheme.bodySmall)),
          ?action,
        ],
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
        children: [Flexible(child: Text(label, style: style)), Text(value, style: style)],
      ),
    );
  }
}
