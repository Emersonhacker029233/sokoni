import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../../core/l10n/gen/app_localizations.dart';
import '../../../../core/router/routes.dart';
import '../../../../core/theme/dimens.dart';
import '../../../legal/legal_content.dart';
import '../../providers/create_account_providers.dart';

/// Step 3 — Terms. Acceptance is recorded exactly as it is elsewhere in
/// the app (same `sokoniTermsVersion` constant), just not sent to the
/// server yet — no account exists until Step 4 succeeds, so this only
/// records the acceptance into the local draft; `CreateAccountController.
/// submit()` sends `terms_version` alongside everything else in the one
/// request that actually creates the account.
class CreateAccountStep3Terms extends ConsumerStatefulWidget {
  const CreateAccountStep3Terms({required this.onSubmitted, super.key});

  final VoidCallback onSubmitted;

  @override
  ConsumerState<CreateAccountStep3Terms> createState() => _CreateAccountStep3TermsState();
}

class _CreateAccountStep3TermsState extends ConsumerState<CreateAccountStep3Terms> {
  bool _accepted = false;
  bool _marketingConsent = false;

  Future<void> _continue() async {
    await ref.read(createAccountProvider.notifier).acceptTerms(sokoniTermsVersion);
    widget.onSubmitted();
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);

    return Padding(
      padding: const EdgeInsets.all(SokoniDimens.space20),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(l10n.legalAcceptanceTitle, style: Theme.of(context).textTheme.headlineSmall),
          const SizedBox(height: SokoniDimens.space16),
          Text(l10n.legalAcceptanceBody, style: Theme.of(context).textTheme.bodyMedium),
          const SizedBox(height: SokoniDimens.space20),
          OutlinedButton(
            onPressed: () => context.push(SokoniRoutes.terms),
            child: Text(l10n.legalTermsTitle),
          ),
          const SizedBox(height: SokoniDimens.space8),
          OutlinedButton(
            onPressed: () => context.push(SokoniRoutes.privacy),
            child: Text(l10n.legalPrivacyTitle),
          ),
          const SizedBox(height: SokoniDimens.space20),
          CheckboxListTile(
            contentPadding: EdgeInsets.zero,
            value: _accepted,
            onChanged: (value) => setState(() => _accepted = value ?? false),
            title: Text(l10n.legalAcceptCheckbox),
            controlAffinity: ListTileControlAffinity.leading,
          ),
          CheckboxListTile(
            contentPadding: EdgeInsets.zero,
            value: _marketingConsent,
            onChanged: (value) {
              setState(() => _marketingConsent = value ?? false);
              ref.read(createAccountProvider.notifier).setMarketingConsent(_marketingConsent);
            },
            title: Text(l10n.legalMarketingConsentCheckbox),
            controlAffinity: ListTileControlAffinity.leading,
          ),
          const Spacer(),
          FilledButton(
            onPressed: _accepted ? _continue : null,
            child: Text(l10n.legalContinue),
          ),
        ],
      ),
    );
  }
}
