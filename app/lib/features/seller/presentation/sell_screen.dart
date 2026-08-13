import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/l10n/gen/app_localizations.dart';
import '../../../core/providers.dart';
import '../../../shared/widgets/empty_state.dart';
import '../../auth/presentation/phone_sign_in_sheet.dart';

/// Centre nav tab ("+"). Browsing works without an account, but selling
/// requires one (CLAUDE.md feature 4) — the 4-step onboarding wizard
/// itself is Phase 6.
class SellScreen extends ConsumerWidget {
  const SellScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = AppLocalizations.of(context);
    final authState = ref.watch(authStateProvider);

    return Scaffold(
      appBar: AppBar(title: Text(l10n.navSell)),
      body: authState.isAuthenticated
          ? SokoniEmptyState(
              icon: Icons.storefront_outlined,
              title: l10n.navSell,
              message: l10n.sellerWizardComingSoon,
            )
          : SokoniEmptyState(
              icon: Icons.lock_outline_rounded,
              title: l10n.profileSignInTitle,
              message: l10n.sellSignInPrompt,
              actionLabel: l10n.profileSignInAction,
              onAction: () => showPhoneSignInSheet(context),
            ),
    );
  }
}
