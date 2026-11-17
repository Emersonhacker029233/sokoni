import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/l10n/gen/app_localizations.dart';
import '../../../core/network/api_exception.dart';
import '../../../core/theme/dimens.dart';
import '../../auth/providers/auth_providers.dart';
import '../legal_content.dart';
import 'terms_agreement_panel.dart';

/// Shown to a signed-in account that hasn't agreed to the current Terms
/// version (CLAUDE.md feature 11: "acceptance recorded (timestamp +
/// version)"; Apple Guideline 1.2: Terms 1.1 with the zero-tolerance
/// clause). Opened after sign-in (`ensureTermsAccepted`) and at app start
/// (`termsOutdatedProvider`, via the app shell). Blocking by design —
/// there's no way to dismiss it without agreeing.
class TermsAcceptanceScreen extends ConsumerStatefulWidget {
  const TermsAcceptanceScreen({super.key});

  @override
  ConsumerState<TermsAcceptanceScreen> createState() => _TermsAcceptanceScreenState();
}

class _TermsAcceptanceScreenState extends ConsumerState<TermsAcceptanceScreen> {
  bool _submitting = false;
  String? _error;

  Future<void> _agree() async {
    setState(() {
      _submitting = true;
      _error = null;
    });
    try {
      await ref.read(authRepositoryProvider).acceptTerms(sokoniTermsVersion);
      ref.invalidate(currentUserProvider);
      // This screen wraps itself in `PopScope(canPop: false)` so the system
      // back button/gesture can't dismiss it before terms are accepted —
      // but that also makes `Navigator.maybePop()` a no-op here, since it
      // explicitly honours `PopScope.canPop` the same way a back gesture
      // would (confirmed against the Flutter SDK's own `NavigatorState`
      // source: `maybePop` checks `popDisposition`, `pop` doesn't). The
      // acceptance call above already succeeded and persisted server-side
      // at this point — `pop()` is the documented escape hatch for exactly
      // this "block back navigation, but still allow leaving via my own
      // button" shape, and is what actually closes this route.
      if (mounted) context.pop();
    } on ApiException catch (e) {
      setState(() => _error = e.message);
    } catch (e) {
      // Never fail silently: anything other than a typed ApiException
      // (e.g. an unexpected response shape) must still surface an error
      // and leave the retry button live, not vanish into an unhandled
      // Future error while the screen sits there looking like nothing
      // happened.
      setState(() => _error = const ServerException().message);
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
        appBar: AppBar(title: Text(l10n.legalTermsTitle), automaticallyImplyLeading: false),
        body: SingleChildScrollView(
          padding: const EdgeInsets.all(SokoniDimens.space20),
          child: TermsAgreementPanel(
            title: l10n.termsUpdatedTitle,
            intro: l10n.termsUpdatedIntro,
            onAgree: _agree,
            busy: _submitting,
            error: _error,
          ),
        ),
      ),
    );
  }
}
