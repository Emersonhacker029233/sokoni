import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../../../core/l10n/gen/app_localizations.dart';
import '../../../core/router/routes.dart';
import '../../../core/theme/dimens.dart';

/// The Terms of Use summary with one clear "Agree and continue" button
/// (Apple Guideline 1.2: users agree to terms that make clear there is no
/// tolerance for objectionable content or abusive users, before they
/// register or sign in). Used on the sign-in sheet's first step, on the
/// "our Terms have changed" screen, and in the create-account wizard.
///
/// [busy] shows a spinner on the button while the caller records the
/// agreement; [error] shows under it, leaving the button live to retry.
class TermsAgreementPanel extends StatelessWidget {
  const TermsAgreementPanel({
    required this.title,
    required this.intro,
    required this.onAgree,
    this.busy = false,
    this.error,
    super.key,
  });

  final String title;
  final String intro;
  final VoidCallback onAgree;
  final bool busy;
  final String? error;

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    final textTheme = Theme.of(context).textTheme;

    return Column(
      mainAxisSize: MainAxisSize.min,
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Text(title, style: textTheme.titleLarge),
        const SizedBox(height: SokoniDimens.space8),
        Text(intro, style: textTheme.bodyMedium),
        const SizedBox(height: SokoniDimens.space16),
        _Point(icon: Icons.gpp_bad_outlined, title: l10n.termsGateZeroToleranceTitle, body: l10n.termsGateZeroToleranceBody),
        _Point(icon: Icons.flag_outlined, title: l10n.termsGateReportBlockTitle, body: l10n.termsGateReportBlockBody),
        _Point(icon: Icons.schedule_rounded, title: l10n.termsGate24hTitle, body: l10n.termsGate24hBody),
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
        const SizedBox(height: SokoniDimens.space8),
        Text(l10n.termsGateAgreeNote, style: textTheme.bodySmall),
        if (error != null) ...[
          const SizedBox(height: SokoniDimens.space8),
          Text(error!, style: TextStyle(color: Theme.of(context).colorScheme.error)),
        ],
        const SizedBox(height: SokoniDimens.space12),
        FilledButton(
          onPressed: busy ? null : onAgree,
          child: busy
              ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2))
              : Text(l10n.termsGateAgree),
        ),
      ],
    );
  }
}

class _Point extends StatelessWidget {
  const _Point({required this.icon, required this.title, required this.body});

  final IconData icon;
  final String title;
  final String body;

  @override
  Widget build(BuildContext context) {
    final textTheme = Theme.of(context).textTheme;

    return Padding(
      padding: const EdgeInsets.only(bottom: SokoniDimens.space12),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(icon, size: 22),
          const SizedBox(width: SokoniDimens.space12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(title, style: textTheme.titleSmall),
                const SizedBox(height: 2),
                Text(body, style: textTheme.bodySmall),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
