import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/l10n/gen/app_localizations.dart';
import '../../../core/network/api_exception.dart';
import '../../../core/theme/dimens.dart';
import '../../../core/utils/formatters.dart';
import '../../../data/models/product.dart';
import '../providers/social_providers.dart';
import 'widgets/my_product_picker_sheet.dart';

/// "Offer"/"Punguzo" composer (CLAUDE.md Part 3) — a time-limited deal on
/// one of the seller's own Listings, countdown 1-7 days.
class OfferComposerScreen extends ConsumerStatefulWidget {
  const OfferComposerScreen({super.key});

  @override
  ConsumerState<OfferComposerScreen> createState() => _OfferComposerScreenState();
}

class _OfferComposerScreenState extends ConsumerState<OfferComposerScreen> {
  final _valueController = TextEditingController();
  Product? _product;
  String _discountType = 'percent';
  int _durationDays = 3;
  bool _submitting = false;
  String? _error;

  @override
  void dispose() {
    _valueController.dispose();
    super.dispose();
  }

  int? get _discountedPrice {
    final product = _product;
    final value = num.tryParse(_valueController.text);
    if (product == null || value == null) return null;
    return _discountType == 'percent'
        ? (product.price * (1 - value / 100)).round()
        : value.round();
  }

  Future<void> _pickProduct() async {
    final product = await showMyProductPickerSheet(context);
    if (product != null) setState(() => _product = product);
  }

  Future<void> _submit() async {
    final product = _product;
    final value = num.tryParse(_valueController.text);
    if (product == null || value == null) return;

    setState(() {
      _submitting = true;
      _error = null;
    });
    try {
      await ref
          .read(offersRepositoryProvider)
          .create(productId: product.id, discountType: _discountType, discountValue: value, durationDays: _durationDays);
      if (!mounted) return;
      ref.invalidate(offersRowProvider);
      Navigator.of(context).pop();
    } on ApiException catch (e) {
      setState(() => _error = e.message);
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    final product = _product;
    final discountedPrice = _discountedPrice;

    return Scaffold(
      appBar: AppBar(title: Text(l10n.offerComposerTitle)),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(SokoniDimens.space20),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            if (product != null) ...[
              ListTile(
                contentPadding: EdgeInsets.zero,
                title: Text(product.title),
                subtitle: Text(l10n.offerComposerCurrentPrice(SokoniFormat.tzs(product.price))),
                trailing: TextButton(onPressed: _pickProduct, child: Text(l10n.offerComposerChooseProduct)),
              ),
            ] else
              OutlinedButton.icon(
                onPressed: _pickProduct,
                icon: const Icon(Icons.inventory_2_outlined),
                label: Text(l10n.offerComposerChooseProduct),
              ),
            const SizedBox(height: SokoniDimens.space20),
            Text(l10n.offerComposerDiscountType, style: Theme.of(context).textTheme.titleMedium),
            const SizedBox(height: SokoniDimens.space8),
            SegmentedButton<String>(
              segments: [
                ButtonSegment(value: 'percent', label: Text(l10n.offerComposerPercent)),
                ButtonSegment(value: 'fixed_price', label: Text(l10n.offerComposerFixedPrice)),
              ],
              selected: {_discountType},
              onSelectionChanged: (selection) => setState(() => _discountType = selection.first),
            ),
            const SizedBox(height: SokoniDimens.space16),
            TextField(
              controller: _valueController,
              keyboardType: TextInputType.number,
              onChanged: (_) => setState(() {}),
              decoration: InputDecoration(
                labelText: _discountType == 'percent'
                    ? l10n.offerComposerDiscountValueLabelPercent
                    : l10n.offerComposerDiscountValueLabelFixed,
              ),
            ),
            if (discountedPrice != null) ...[
              const SizedBox(height: SokoniDimens.space8),
              Text(l10n.offerComposerNewPrice(SokoniFormat.tzs(discountedPrice))),
            ],
            const SizedBox(height: SokoniDimens.space20),
            Text(l10n.offerComposerDurationLabel(_durationDays), style: Theme.of(context).textTheme.titleMedium),
            Slider(
              value: _durationDays.toDouble(),
              min: 1,
              max: 7,
              divisions: 6,
              label: '$_durationDays',
              onChanged: (value) => setState(() => _durationDays = value.round()),
            ),
            if (_error != null) ...[
              const SizedBox(height: SokoniDimens.space8),
              Text(_error!, style: const TextStyle(color: Colors.red, fontSize: 13)),
            ],
            const SizedBox(height: SokoniDimens.space20),
            FilledButton(
              onPressed: (_submitting || product == null || discountedPrice == null) ? null : _submit,
              child: _submitting
                  ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2))
                  : Text(l10n.offerComposerSubmit),
            ),
          ],
        ),
      ),
    );
  }
}
