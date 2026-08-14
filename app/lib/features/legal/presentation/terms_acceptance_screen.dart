import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/l10n/gen/app_localizations.dart';
import '../../../core/network/api_exception.dart';
import '../../../core/router/routes.dart';
import '../../../core/theme/dimens.dart';
import '../../auth/providers/auth_providers.dart';
import '../legal_content.dart';

/// Shown once, right after a new account's first sign-in (CLAUDE.md
/// feature 11: "Terms and Privacy screens with acceptance recorded
/// (timestamp + version) at signup"). Blocking by design — there's no
/// way to dismiss it without accepting, since `terms_accepted_at` gates
/// nothing else server-side and this is the only enforcement point.
class TermsAcceptanceScreen extends ConsumerStatefulWidget {
  const TermsAcceptanceScreen({super.key});

  @override
  ConsumerState<TermsAcceptanceScreen> createState() => _TermsAcceptanceScreenState();
}

class _TermsAcceptanceScreenState extends ConsumerState<TermsAcceptanceScreen> {
  bool _accepted = false;
  bool _submitting = false;
  String? _error;

  Future<void> _continue() async {
    setState(() {
      _submitting = true;
      _error = null;
    });
    try {
      await ref.read(authRepositoryProvider).acceptTerms(sokoniTermsVersion);
      if (mounted) Navigator.of(context).maybePop();
    } on ApiException catch (e) {
      setState(() => _error = e.message);
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);

    return PopScope(
      canPop: false,
      child: Scaffold(
        appBar: AppBar(title: Text(l10n.legalAcceptanceTitle), automaticallyImplyLeading: false),
        body: Padding(
          padding: const EdgeInsets.all(SokoniDimens.space20),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
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
              if (_error != null) ...[
                const SizedBox(height: SokoniDimens.space8),
                Text(_error!, style: const TextStyle(color: Colors.red)),
              ],
              const Spacer(),
              FilledButton(
                onPressed: (_accepted && !_submitting) ? _continue : null,
                child: _submitting
                    ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2))
                    : Text(l10n.legalContinue),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
