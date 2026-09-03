import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/l10n/gen/app_localizations.dart';
import '../../../core/providers.dart';
import '../../../core/router/routes.dart';
import '../../../core/theme/dimens.dart';
import '../../../features/auth/providers/auth_providers.dart';
import '../../../shared/widgets/empty_state.dart';
import '../../../shared/widgets/error_state.dart';
import '../../auth/presentation/auth_entry_sheet.dart';

class ProfileScreen extends ConsumerWidget {
  const ProfileScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = AppLocalizations.of(context);
    final authState = ref.watch(authStateProvider);

    return Scaffold(
      appBar: AppBar(title: Text(l10n.navProfile)),
      body: authState.isAuthenticated
          ? _SignedInProfile()
          : authState.isUnknown
              ? SokoniErrorState(
                  message: l10n.authSessionUnknownBody,
                  onRetry: () => ref.read(authStateProvider.notifier).retry(),
                )
              : SokoniEmptyState(
                  icon: Icons.person_outline_rounded,
                  title: l10n.profileSignInTitle,
                  message: l10n.profileSignInBody,
                  actionLabel: l10n.profileSignInAction,
                  onAction: () => showAuthEntrySheet(context),
                ),
    );
  }
}

class _SignedInProfile extends ConsumerWidget {
  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = AppLocalizations.of(context);

    final userAsync = ref.watch(currentUserProvider);

    return userAsync.when(
      loading: () => const Center(child: CircularProgressIndicator()),
      error: (error, _) => SokoniErrorState(
        message: '$error',
        onRetry: () => ref.invalidate(currentUserProvider),
      ),
      data: (user) {
        return Padding(
          padding: const EdgeInsets.all(SokoniDimens.space20),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(l10n.profileSignedInAs(user.name), style: Theme.of(context).textTheme.titleMedium),
              const SizedBox(height: SokoniDimens.space24),
              ListTile(
                contentPadding: EdgeInsets.zero,
                leading: const Icon(Icons.chat_bubble_outline_rounded),
                title: Text(l10n.chatTitle),
                trailing: const Icon(Icons.chevron_right_rounded),
                onTap: () => context.push(SokoniRoutes.conversations),
              ),
              ListTile(
                contentPadding: EdgeInsets.zero,
                leading: const Icon(Icons.favorite_border_rounded),
                title: Text(l10n.favoritesTitle),
                trailing: const Icon(Icons.chevron_right_rounded),
                onTap: () => context.push(SokoniRoutes.favorites),
              ),
              ListTile(
                contentPadding: EdgeInsets.zero,
                leading: const Icon(Icons.settings_outlined),
                title: Text(l10n.profileSettings),
                trailing: const Icon(Icons.chevron_right_rounded),
                onTap: () => context.push(SokoniRoutes.settings),
              ),
              const SizedBox(height: SokoniDimens.space12),
              // Now that the "+" FAB opens the create sheet instead of
              // navigating to the Sell branch directly (CLAUDE.md Part 3),
              // this is the one remaining entry point back into "My Shop"
              // for an existing seller — go(), not push(), since it's a
              // StatefulShellRoute branch, not a regular pushed screen.
              OutlinedButton(
                onPressed: () => user.isSeller
                    ? context.go(SokoniRoutes.sell)
                    : context.push(SokoniRoutes.sellerOnboarding),
                child: Text(user.isSeller ? l10n.myShopTitle : l10n.profileStartSelling),
              ),
              const SizedBox(height: SokoniDimens.space12),
              TextButton(
                onPressed: () => ref.read(authStateProvider.notifier).signOut(),
                child: Text(l10n.profileSignOut),
              ),
              const Spacer(),
              Row(
                children: [
                  TextButton(
                    onPressed: () => context.push(SokoniRoutes.terms),
                    child: Text(l10n.legalTermsTitle),
                  ),
                  TextButton(
                    onPressed: () => context.push(SokoniRoutes.privacy),
                    child: Text(l10n.legalPrivacyTitle),
                  ),
                ],
              ),
            ],
          ),
        );
      },
    );
  }
}
