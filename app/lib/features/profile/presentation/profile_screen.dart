import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/l10n/gen/app_localizations.dart';
import '../../../core/providers.dart';
import '../../../core/theme/dimens.dart';
import '../../../data/models/user.dart';
import '../../../features/auth/providers/auth_providers.dart';
import '../../../shared/widgets/empty_state.dart';
import '../../auth/presentation/phone_sign_in_sheet.dart';

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
          : SokoniEmptyState(
              icon: Icons.person_outline_rounded,
              title: l10n.profileSignInTitle,
              message: l10n.profileSignInBody,
              actionLabel: l10n.profileSignInAction,
              onAction: () => showPhoneSignInSheet(context),
            ),
    );
  }
}

class _SignedInProfile extends ConsumerWidget {
  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = AppLocalizations.of(context);

    return FutureBuilder<SokoniUser>(
      future: ref.read(authRepositoryProvider).me(),
      builder: (context, snapshot) {
        if (!snapshot.hasData) {
          return const Center(child: CircularProgressIndicator());
        }
        final user = snapshot.data!;
        return Padding(
          padding: const EdgeInsets.all(SokoniDimens.space20),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(l10n.profileSignedInAs(user.name), style: Theme.of(context).textTheme.titleMedium),
              const SizedBox(height: SokoniDimens.space24),
              OutlinedButton(
                onPressed: () => ScaffoldMessenger.of(context).showSnackBar(
                  SnackBar(content: Text(l10n.sellerWizardComingSoon)),
                ),
                child: Text(l10n.profileStartSelling),
              ),
              const SizedBox(height: SokoniDimens.space12),
              TextButton(
                onPressed: () => ref.read(authStateProvider.notifier).signOut(),
                child: Text(l10n.profileSignOut),
              ),
            ],
          ),
        );
      },
    );
  }
}
