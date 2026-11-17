import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../../core/l10n/gen/app_localizations.dart';
import '../../../../core/router/routes.dart';
import '../../../../core/theme/dimens.dart';
import '../../../legal/legal_content.dart';
import '../../../legal/presentation/terms_agreement_panel.dart';
import '../../../legal/providers/terms_agreement.dart';
import '../../providers/create_account_providers.dart';

/// Step 3 — Terms. Acceptance is recorded exactly as it is elsewhere in
/// the app (same `sokoniTermsVersion` constant), just not sent to the
/// server yet — no account exists until Step 4 succeeds, so this only
/// records the acceptance into the local draft; `CreateAccountController.
/// submit()` sends `terms_version` alongside everything else in the one
/// request that actually creates the account.
///
/// Apple Guideline 1.2: the sign-in sheet's Terms step comes before the
/// "Create an account" option, so normally the person has already agreed
/// and this step only confirms it (plus the optional marketing consent).
/// Reached any other way, it shows the same single Agree step first.
class CreateAccountStep3Terms extends ConsumerStatefulWidget {
  const CreateAccountStep3Terms({required this.onSubmitted, super.key});

  final VoidCallback onSubmitted;

  @override
  ConsumerState<CreateAccountStep3Terms> createState() => _CreateAccountStep3TermsState();
}

class _CreateAccountStep3TermsState extends ConsumerState<CreateAccountStep3Terms> {
  bool _marketingConsent = false;

  Future<void> _continue() async {
    await ref.read(createAccountProvider.notifier).acceptTerms(sokoniTermsVersion);
    widget.onSubmitted();
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    final agreed = ref.watch(signInTermsAgreementProvider) == sokoniTermsVersion;

    if (!agreed) {
      return SingleChildScrollView(
        padding: const EdgeInsets.all(SokoniDimens.space20),
        child: TermsAgreementPanel(
          title: l10n.termsGateTitle,
          intro: l10n.termsGateIntro,
          onAgree: () => ref.read(signInTermsAgreementProvider.notifier).agree(),
        ),
      );
    }

    return Padding(
      padding: const EdgeInsets.all(SokoniDimens.space20),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(l10n.legalAcceptanceTitle, style: Theme.of(context).textTheme.headlineSmall),
          const SizedBox(height: SokoniDimens.space16),
          Row(
            children: [
              const Icon(Icons.check_circle_rounded),
              const SizedBox(width: SokoniDimens.space12),
              Expanded(child: Text(l10n.termsAgreedNote(sokoniTermsVersion))),
            ],
          ),
          const SizedBox(height: SokoniDimens.space8),
          Wrap(
            spacing: SokoniDimens.space8,
            children: [
              TextButton(
                onPressed: () => context.push(SokoniRoutes.terms),
                child: Text(l10n.termsGateReadFull),
              ),
              TextButton(
                onPressed: () => context.push(SokoniRoutes.privacy),
                child: Text(l10n.legalPrivacyTitle),
              ),
            ],
          ),
          const SizedBox(height: SokoniDimens.space12),
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
            onPressed: _continue,
            child: Text(l10n.legalContinue),
          ),
        ],
      ),
    );
  }
}
