import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../../core/l10n/gen/app_localizations.dart';
import '../../../../core/theme/colors.dart';
import '../../../../core/theme/dimens.dart';
import '../../providers/create_account_providers.dart';

/// Step 1 — "What brings you to Sokoni?" Two large cards, not a dropdown
/// (CLAUDE.md restructure, 2026-08-25) — the account-type choice used to
/// arrive last, after everything else; testers found that backwards, so
/// it's the very first thing asked now.
class CreateAccountStep1Intent extends ConsumerWidget {
  const CreateAccountStep1Intent({required this.onSubmitted, super.key});

  final VoidCallback onSubmitted;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = AppLocalizations.of(context);

    Future<void> choose(String intent) async {
      await ref.read(createAccountProvider.notifier).chooseIntent(intent);
      onSubmitted();
    }

    return SingleChildScrollView(
      padding: const EdgeInsets.all(SokoniDimens.space20),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(l10n.createAccountIntentTitle, style: Theme.of(context).textTheme.headlineSmall),
          const SizedBox(height: SokoniDimens.space24),
          _IntentCard(
            icon: Icons.shopping_bag_outlined,
            title: l10n.createAccountIntentBuyTitle,
            description: l10n.createAccountIntentBuyDescription,
            onTap: () => choose('buy'),
          ),
          const SizedBox(height: SokoniDimens.space16),
          _IntentCard(
            icon: Icons.storefront_outlined,
            title: l10n.createAccountIntentSellTitle,
            description: l10n.createAccountIntentSellDescription,
            onTap: () => choose('sell'),
          ),
        ],
      ),
    );
  }
}

class _IntentCard extends StatelessWidget {
  const _IntentCard({required this.icon, required this.title, required this.description, required this.onTap});

  final IconData icon;
  final String title;
  final String description;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;

    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(SokoniDimens.radiusCard),
      child: Container(
        width: double.infinity,
        padding: const EdgeInsets.all(SokoniDimens.space20),
        decoration: BoxDecoration(
          border: Border.all(color: Theme.of(context).dividerColor),
          borderRadius: BorderRadius.circular(SokoniDimens.radiusCard),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Icon(icon, size: 36, color: SokoniColors.sokoniYellow),
            const SizedBox(height: SokoniDimens.space12),
            Text(title, style: Theme.of(context).textTheme.titleLarge),
            const SizedBox(height: SokoniDimens.space4),
            Text(
              description,
              style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                color: isDark ? Colors.white70 : Colors.black54,
              ),
            ),
          ],
        ),
      ),
    );
  }
}
