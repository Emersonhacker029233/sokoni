import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../../core/l10n/gen/app_localizations.dart';
import '../../../../core/network/api_exception.dart';
import '../../../../core/theme/dimens.dart';
import '../../../../core/utils/formatters.dart';
import '../../../../core/utils/validators.dart';
import '../../../../data/models/category.dart';
import '../../../../data/models/seller_profile.dart';
import '../../../discovery/providers/discovery_providers.dart';
import '../../providers/seller_onboarding_providers.dart';

class OnboardingStep1Business extends ConsumerStatefulWidget {
  const OnboardingStep1Business({required this.onSubmitted, super.key});

  final ValueChanged<SellerProfile> onSubmitted;

  @override
  ConsumerState<OnboardingStep1Business> createState() => _OnboardingStep1BusinessState();
}

class _OnboardingStep1BusinessState extends ConsumerState<OnboardingStep1Business> {
  final _formKey = GlobalKey<FormState>();
  final _shopNameController = TextEditingController();
  final _handleController = TextEditingController();
  final _bioController = TextEditingController();
  final _whatsappController = TextEditingController();
  int? _categoryId;
  bool _submitting = false;
  String? _error;

  @override
  void dispose() {
    _shopNameController.dispose();
    _handleController.dispose();
    _bioController.dispose();
    _whatsappController.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (!(_formKey.currentState?.validate() ?? false) || _categoryId == null) return;
    setState(() {
      _submitting = true;
      _error = null;
    });
    try {
      final e164Whatsapp = _whatsappController.text.trim().isEmpty
          ? null
          : SokoniFormat.phoneToE164(_whatsappController.text.trim());
      final seller = await ref.read(sellerOnboardingProvider.notifier).submitBusiness(
        shopName: _shopNameController.text.trim(),
        handle: _handleController.text.trim(),
        categoryId: _categoryId!,
        bio: _bioController.text.trim().isEmpty ? null : _bioController.text.trim(),
        whatsapp: e164Whatsapp,
      );
      widget.onSubmitted(seller);
    } on ApiException catch (e) {
      setState(() => _error = e.message);
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    final categoriesAsync = ref.watch(categoriesProvider);
    final locale = Localizations.localeOf(context).languageCode;

    return SingleChildScrollView(
      padding: const EdgeInsets.all(SokoniDimens.space20),
      child: Form(
        key: _formKey,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            TextFormField(
              controller: _shopNameController,
              decoration: InputDecoration(labelText: l10n.onboardingShopName),
              validator: (v) => SokoniValidators.required(v),
            ),
            const SizedBox(height: SokoniDimens.space16),
            TextFormField(
              controller: _handleController,
              decoration: InputDecoration(
                labelText: l10n.onboardingHandle,
                hintText: l10n.onboardingHandleHint,
                prefixText: '@',
              ),
              validator: SokoniValidators.handle,
            ),
            const SizedBox(height: SokoniDimens.space16),
            categoriesAsync.when(
              loading: () => const LinearProgressIndicator(),
              error: (e, _) => Row(
                children: [
                  Expanded(child: Text('$e', style: const TextStyle(color: Colors.red))),
                  TextButton(
                    onPressed: () => ref.invalidate(categoriesProvider),
                    child: Text(l10n.commonRetry),
                  ),
                ],
              ),
              data: (categories) => DropdownButtonFormField<int>(
                initialValue: _categoryId,
                decoration: InputDecoration(labelText: l10n.onboardingCategory),
                items: [
                  for (final c in categories)
                    DropdownMenuItem(value: c.id, child: Text(c.name(locale))),
                ],
                onChanged: (value) => setState(() => _categoryId = value),
                validator: (v) => v == null ? l10n.onboardingCategory : null,
              ),
            ),
            const SizedBox(height: SokoniDimens.space16),
            TextFormField(
              controller: _bioController,
              maxLines: 3,
              decoration: InputDecoration(labelText: l10n.onboardingBio),
            ),
            const SizedBox(height: SokoniDimens.space16),
            TextFormField(
              controller: _whatsappController,
              keyboardType: TextInputType.phone,
              decoration: InputDecoration(labelText: l10n.onboardingWhatsapp),
            ),
            if (_error != null) ...[
              const SizedBox(height: SokoniDimens.space8),
              Text(_error!, style: const TextStyle(color: Colors.red)),
            ],
            const SizedBox(height: SokoniDimens.space24),
            FilledButton(
              onPressed: _submitting ? null : _submit,
              child: _submitting
                  ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2))
                  : Text(l10n.onboardingNext),
            ),
          ],
        ),
      ),
    );
  }
}
