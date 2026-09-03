import 'package:flutter/material.dart';

import '../../../core/l10n/gen/app_localizations.dart';
import '../../../core/motion/sokoni_bottom_sheet.dart';
import '../../../core/theme/colors.dart';
import '../../../core/theme/dimens.dart';
import 'auth_entry_sheet.dart';

/// Shown wherever a guest attempts something that genuinely needs an
/// account — save, follow, message, order. An invitation, not an error:
/// no red text, no "failed" language, just the reason to sign in and a
/// clean way out. Every guest-gated action in the app should route its 401
/// here rather than showing a generic failure message (see
/// `favorites_providers.dart`, `seller_providers.dart`, the chat buttons in
/// `feed_card.dart`/`product_detail_screen.dart`, and `checkout_screen.dart`
/// — DECISIONS.md has the full audit).
Future<void> showSignInPrompt(BuildContext context, {required String message}) {
  return showSokoniBottomSheet<void>(
    context: context,
    initialChildSize: 0.32,
    minChildSize: 0.28,
    maxChildSize: 0.5,
    builder: (context) => _SignInPromptBody(message: message),
  );
}

class _SignInPromptBody extends StatelessWidget {
  const _SignInPromptBody({required this.message});

  final String message;

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    final textTheme = Theme.of(context).textTheme;

    return Column(
      mainAxisSize: MainAxisSize.min,
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        const Icon(Icons.favorite_rounded, size: 40, color: SokoniColors.sokoniYellow),
        const SizedBox(height: SokoniDimens.space16),
        Text(message, style: textTheme.titleMedium, textAlign: TextAlign.center),
        const SizedBox(height: SokoniDimens.space24),
        FilledButton(
          onPressed: () {
            Navigator.of(context).pop();
            showAuthEntrySheet(context);
          },
          child: Text(l10n.profileSignInAction),
        ),
        const SizedBox(height: SokoniDimens.space8),
        TextButton(
          onPressed: () => Navigator.of(context).pop(),
          child: Text(l10n.guestPromptDismiss),
        ),
      ],
    );
  }
}
