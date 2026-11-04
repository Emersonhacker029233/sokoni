import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/l10n/gen/app_localizations.dart';
import '../../../shared/widgets/empty_state.dart';
import '../../../shared/widgets/error_state.dart';
import '../../../shared/widgets/sokoni_avatar.dart';
import '../providers/block_providers.dart';
import 'moderation_menu.dart';

/// Settings → Blocked accounts: everyone the user blocked, each with
/// Unblock (Apple Guideline 1.2).
class BlockedAccountsScreen extends ConsumerWidget {
  const BlockedAccountsScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = AppLocalizations.of(context);
    final blocked = ref.watch(blockedAccountsProvider);

    return Scaffold(
      appBar: AppBar(title: Text(l10n.blockedAccountsTitle)),
      body: blocked.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (error, _) => SokoniErrorState(
          message: error.toString(),
          onRetry: () => ref.invalidate(blockedAccountsProvider),
        ),
        data: (data) {
          if (data.accounts.isEmpty) {
            return SokoniEmptyState(
              icon: Icons.verified_user_outlined,
              title: l10n.blockedAccountsTitle,
              message: l10n.blockedAccountsEmptyBody,
            );
          }

          return ListView.separated(
            itemCount: data.accounts.length,
            separatorBuilder: (_, _) => const Divider(height: 1),
            itemBuilder: (context, index) {
              final account = data.accounts[index];
              return ListTile(
                leading: SokoniAvatar(
                  imageUrl: account.logo ?? account.avatar,
                  radius: 20,
                  fallbackIcon: account.sellerId != null ? Icons.storefront_rounded : Icons.person_rounded,
                ),
                title: Text(account.displayName),
                subtitle: account.handle != null ? Text('@${account.handle}') : null,
                trailing: OutlinedButton(
                  onPressed: () => unblockWithFeedback(context, ref, account.userId, account.displayName),
                  child: Text(l10n.unblockAction),
                ),
              );
            },
          );
        },
      ),
    );
  }
}
