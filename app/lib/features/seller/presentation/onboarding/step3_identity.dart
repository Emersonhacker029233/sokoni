import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../../core/l10n/gen/app_localizations.dart';
import '../../../../core/network/api_exception.dart';
import '../../../../core/theme/dimens.dart';
import '../../../../core/utils/validators.dart';
import '../../../../data/models/seller_profile.dart';
import '../../providers/seller_onboarding_providers.dart';

/// Step 3 (final step — B1/B2, tester feedback: the ID photo and business
/// licence steps are gone entirely): NIDA number (CLAUDE.md — 20 digits,
/// format-validated only; live verification is out of scope, see
/// NidaVerifier / BLOCKERS.md). The typed number alone is the whole
/// submission.
class OnboardingStep3Identity extends ConsumerStatefulWidget {
  const OnboardingStep3Identity({required this.sellerId, required this.onSubmitted, super.key});

  final int? sellerId;
  final ValueChanged<SellerProfile> onSubmitted;

  @override
  ConsumerState<OnboardingStep3Identity> createState() => _OnboardingStep3IdentityState();
}

class _OnboardingStep3IdentityState extends ConsumerState<OnboardingStep3Identity> {
  final _formKey = GlobalKey<FormState>();
  final _nidaController = TextEditingController();
  bool _submitting = false;
  String? _error;

  @override
  void dispose() {
    _nidaController.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (!(_formKey.currentState?.validate() ?? false) || widget.sellerId == null) return;
    setState(() {
      _submitting = true;
      _error = null;
    });
    try {
      final seller = await ref.read(sellerOnboardingProvider.notifier).submitIdentity(
        sellerId: widget.sellerId!,
        nidaNumber: _nidaController.text.trim(),
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

    return SingleChildScrollView(
      padding: const EdgeInsets.all(SokoniDimens.space20),
      child: Form(
        key: _formKey,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            TextFormField(
              controller: _nidaController,
              keyboardType: TextInputType.number,
              maxLength: 20,
              decoration: InputDecoration(labelText: l10n.onboardingNidaNumber),
              validator: SokoniValidators.nidaNumber,
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
                  : Text(l10n.onboardingSubmit),
            ),
          ],
        ),
      ),
    );
  }
}
