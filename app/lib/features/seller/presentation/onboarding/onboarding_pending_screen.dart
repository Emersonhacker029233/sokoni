import 'package:flutter/material.dart';

import '../../../../core/l10n/gen/app_localizations.dart';
import '../../../../core/motion/success_checkmark.dart';

/// Wizard completion moment (CLAUDE.md motion primitive 8: success
/// moments) — a full-screen drawn checkmark for "you're all set". The
/// pending-verification explanation itself lives in the My Shop tab's own
/// status view (CLAUDE.md feature 4: products can be created immediately
/// but stay hidden from the public feed until an admin verifies the
/// account), so this dialog doesn't need to carry that text too.
Future<void> showOnboardingPendingScreen(BuildContext context) {
  final l10n = AppLocalizations.of(context);
  return showSokoniSuccess(context, message: l10n.onboardingPendingTitle);
}
