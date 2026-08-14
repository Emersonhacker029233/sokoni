import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../../../core/router/routes.dart';
import '../../../data/models/user.dart';

/// Blocks on the Terms/Privacy acceptance screen if [user] hasn't accepted
/// yet (CLAUDE.md feature 11) — called right after every successful
/// sign-in, before the sign-in sheet closes, so a new account can't reach
/// the rest of the app without accepting.
Future<void> ensureTermsAccepted(BuildContext context, SokoniUser user) async {
  if (user.termsAccepted) return;
  await context.push(SokoniRoutes.termsAcceptance);
}
