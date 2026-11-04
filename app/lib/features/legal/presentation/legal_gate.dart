import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../../../core/router/routes.dart';
import '../../../data/models/user.dart';
import '../legal_content.dart';

/// Blocks on the Terms acceptance screen unless [user] has agreed to the
/// Terms version this app ships with (CLAUDE.md feature 11; Apple
/// Guideline 1.2 made it version-aware, so the 1.1 zero-tolerance terms
/// reach every account once). Called right after every successful
/// sign-in, before the sign-in sheet closes. A sign-in that went through
/// the sign-in sheet's Terms step already recorded the current version on
/// the server, so this only ever opens for older paths.
Future<void> ensureTermsAccepted(BuildContext context, SokoniUser user) async {
  if (user.termsVersion == sokoniTermsVersion) return;
  await context.push(SokoniRoutes.termsAcceptance);
}
