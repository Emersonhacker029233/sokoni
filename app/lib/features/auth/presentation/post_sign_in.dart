import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/providers.dart';
import '../../../core/router/routes.dart';
import '../../../data/models/auth_response.dart';
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
Future<void> completeSignIn(BuildContext context, WidgetRef ref, AuthResponse response) async {
  ref.read(authStateProvider.notifier).markAuthenticated();
  if (!context.mounted) return;

  await ensureTermsAccepted(context, response.user);
  if (!context.mounted) return;

  if (response.isNewAccount) {
    context.go(SokoniRoutes.accountIntent);
  } else {
    Navigator.of(context).pop();
  }
}
