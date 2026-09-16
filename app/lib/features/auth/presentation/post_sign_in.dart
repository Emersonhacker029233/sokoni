import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/providers.dart';
import '../../../core/router/routes.dart';
import '../../../data/models/auth_response.dart';
import '../../../main.dart' show restartApp;
import '../../legal/presentation/legal_gate.dart';

/// Shared completion flow for every sign-in method (phone OTP, Google,
/// Apple) — one path so the buy/sell/decide-later intent screen (CLAUDE.md
/// Part 2 item 2) can't drift out of sync between them.
///
/// Order matters: mark authenticated, then Terms/Privacy if this account
/// hasn't accepted yet (CLAUDE.md feature 11), then — only for an account
/// that was just created by *this* sign-in, never a returning user — the
/// one-question intent screen. `context.go` there replaces the whole
/// navigation stack (the sign-in sheet included), landing cleanly on the
/// intent screen rather than leaving it stacked underneath. A returning
/// user skips straight to closing whatever sign-in UI got them here.
///
/// Part 5 (client feedback): this same sheet is also how "Add account"
/// signs into a second account without signing out of the first — the
/// only way to reach a sign-in flow while [authStateProvider] is already
/// `authenticated`. `AuthRepository` has already persisted the new
/// account and made it active (`SokoniSecureStorage.addOrUpdateAccount`
/// always does both) by the time this runs; what's still outstanding is
/// making the REST of the app agree, and since any leftover per-account
/// provider state (the cart, drafts, unread counts) is exactly the
/// leakage this feature was built to prevent, that means a full restart
/// — see `restartApp()`'s own docs in main.dart — not just closing the
/// sheet. The brand-new-account intent screen is deliberately skipped in
/// that case: it's a first-run nicety (see AccountIntentScreen's own
/// docs), not a functional gate, and this is already the rarer edge case
/// within an edge case — creating a brand-new account from inside the
/// switcher, rather than the switcher's real purpose of adding an
/// *existing* one.
Future<void> completeSignIn(BuildContext context, WidgetRef ref, AuthResponse response) async {
  final wasAlreadySignedIn = ref.read(authStateProvider).isAuthenticated;

  ref.read(authStateProvider.notifier).markAuthenticated();
  if (!context.mounted) return;

  await ensureTermsAccepted(context, response.user);
  if (!context.mounted) return;

  if (wasAlreadySignedIn) {
    restartApp();
    return;
  }

  if (response.isNewAccount) {
    context.go(SokoniRoutes.accountIntent);
  } else {
    Navigator.of(context).pop();
  }
}
