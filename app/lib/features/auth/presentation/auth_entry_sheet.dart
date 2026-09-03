import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../../../core/l10n/gen/app_localizations.dart';
import '../../../core/router/routes.dart';
import '../../../core/theme/dimens.dart';
import 'sign_in_sheet.dart';
import 'social_sign_in_buttons.dart';

/// The single entry point every "guest needs to sign in" call site in the
/// app opens (renamed from the old `showPhoneSignInSheet` — CLAUDE.md
/// restructure, 2026-08-25). Shows social sign-in up top (unchanged —
/// Google/Apple already transparently handle both a new and a returning
/// account in one tap) plus the two phone-based paths testers found
/// confusing when they were one combined screen: "Sign in" (existing
/// accounts only — phone, then code, done) and "Create an account" (the
/// full buy/sell wizard, a separate full-screen route since it's
/// materially bigger than anything else a bottom sheet here holds).
Future<void> showAuthEntrySheet(BuildContext context) {
  return showModalBottomSheet<void>(
    context: context,
    isScrollControlled: true,
    useSafeArea: true,
    backgroundColor: Theme.of(context).scaffoldBackgroundColor,
    shape: const RoundedRectangleBorder(
      borderRadius: BorderRadius.vertical(top: Radius.circular(SokoniDimens.radiusSheet)),
    ),
    builder: (context) => const _AuthEntrySheetContent(),
  );
}

class _AuthEntrySheetContent extends StatelessWidget {
  const _AuthEntrySheetContent();

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);

    return Padding(
      padding: EdgeInsets.only(
        left: SokoniDimens.space20,
        right: SokoniDimens.space20,
        top: SokoniDimens.space20,
        bottom: MediaQuery.viewInsetsOf(context).bottom + SokoniDimens.space24,
      ),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text(l10n.phoneSignInHeader, style: Theme.of(context).textTheme.titleLarge),
          const SizedBox(height: SokoniDimens.space16),
          const SocialSignInButtons(),
          const SizedBox(height: SokoniDimens.space16),
          Row(
            children: [
              const Expanded(child: Divider()),
              Padding(
                padding: const EdgeInsets.symmetric(horizontal: SokoniDimens.space12),
                child: Text(l10n.signInOr, style: Theme.of(context).textTheme.bodySmall),
              ),
              const Expanded(child: Divider()),
            ],
          ),
          const SizedBox(height: SokoniDimens.space16),
          _EntryOption(
            icon: Icons.login_rounded,
            title: l10n.authEntrySignInAction,
            subtitle: l10n.authEntrySignInDescription,
            onTap: () {
              Navigator.of(context).pop();
              showSignInSheet(context);
            },
          ),
          const SizedBox(height: SokoniDimens.space12),
          _EntryOption(
            icon: Icons.person_add_alt_1_rounded,
            title: l10n.authEntryCreateAccountAction,
            subtitle: l10n.authEntryCreateAccountDescription,
            onTap: () {
              Navigator.of(context).pop();
              context.push(SokoniRoutes.createAccount);
            },
          ),
        ],
      ),
    );
  }
}

class _EntryOption extends StatelessWidget {
  const _EntryOption({required this.icon, required this.title, required this.subtitle, required this.onTap});

  final IconData icon;
  final String title;
  final String subtitle;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;

    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(SokoniDimens.radiusCard),
      child: Container(
        padding: const EdgeInsets.all(SokoniDimens.space16),
        decoration: BoxDecoration(
          border: Border.all(color: Theme.of(context).dividerColor),
          borderRadius: BorderRadius.circular(SokoniDimens.radiusCard),
        ),
        child: Row(
          children: [
            Icon(icon, size: 28),
            const SizedBox(width: SokoniDimens.space16),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(title, style: Theme.of(context).textTheme.titleMedium),
                  Text(
                    subtitle,
                    style: Theme.of(context).textTheme.bodySmall?.copyWith(
                      color: isDark ? Colors.white70 : Colors.black54,
                    ),
                  ),
                ],
              ),
            ),
            const Icon(Icons.chevron_right_rounded),
          ],
        ),
      ),
    );
  }
}
