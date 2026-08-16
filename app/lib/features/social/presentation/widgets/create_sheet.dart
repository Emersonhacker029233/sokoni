import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../../core/l10n/gen/app_localizations.dart';
import '../../../../core/motion/sokoni_bottom_sheet.dart';
import '../../../../core/router/routes.dart';
import '../../../../core/theme/colors.dart';
import '../../../../core/theme/dimens.dart';
import '../../../auth/providers/auth_providers.dart';

/// Centre "+" create sheet (CLAUDE.md Part 3): Listing/Update/Offer/Showcase
/// for a seller, a "Start selling" prompt for a buyer — same
/// tap-an-option-and-the-sheet-closes idiom as `showRadiusFilterSheet`.
Future<void> showCreateSheet(BuildContext context, WidgetRef ref) async {
  final userAsync = ref.read(currentUserProvider);
  final isSeller = userAsync.value?.isSeller ?? false;

  await showSokoniBottomSheet<void>(
    context: context,
    initialChildSize: isSeller ? 0.5 : 0.3,
    builder: (context) => isSeller ? const _CreateOptions() : const _StartSellingPrompt(),
  );
}

class _CreateOptions extends StatelessWidget {
  const _CreateOptions();

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);

    return Column(
      mainAxisSize: MainAxisSize.min,
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(l10n.createSheetTitle, style: Theme.of(context).textTheme.titleLarge),
        const SizedBox(height: SokoniDimens.space16),
        _CreateOption(
          icon: Icons.inventory_2_outlined,
          title: l10n.createListingTitle,
          subtitle: l10n.createListingSubtitle,
          onTap: () {
            Navigator.of(context).pop();
            context.push(SokoniRoutes.newProduct);
          },
        ),
        _CreateOption(
          icon: Icons.auto_stories_outlined,
          title: l10n.createUpdateTitle,
          subtitle: l10n.createUpdateSubtitle,
          onTap: () {
            Navigator.of(context).pop();
            context.push(SokoniRoutes.newUpdate);
          },
        ),
        _CreateOption(
          icon: Icons.local_offer_outlined,
          title: l10n.createOfferTitle,
          subtitle: l10n.createOfferSubtitle,
          onTap: () {
            Navigator.of(context).pop();
            context.push(SokoniRoutes.newOffer);
          },
        ),
        _CreateOption(
          icon: Icons.movie_creation_outlined,
          title: l10n.createShowcaseTitle,
          subtitle: l10n.createShowcaseSubtitle,
          onTap: () {
            Navigator.of(context).pop();
            context.push(SokoniRoutes.newShowcase);
          },
        ),
      ],
    );
  }
}

class _CreateOption extends StatelessWidget {
  const _CreateOption({required this.icon, required this.title, required this.subtitle, required this.onTap});

  final IconData icon;
  final String title;
  final String subtitle;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return ListTile(
      contentPadding: EdgeInsets.zero,
      leading: Container(
        width: 44,
        height: 44,
        decoration: const BoxDecoration(color: SokoniColors.sokoniYellow, shape: BoxShape.circle),
        alignment: Alignment.center,
        child: Icon(icon, color: SokoniColors.onYellow),
      ),
      title: Text(title),
      subtitle: Text(subtitle),
      onTap: onTap,
    );
  }
}

class _StartSellingPrompt extends StatelessWidget {
  const _StartSellingPrompt();

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);

    return Column(
      mainAxisSize: MainAxisSize.min,
      crossAxisAlignment: CrossAxisAlignment.center,
      children: [
        Text(l10n.createSheetStartSellingTitle, style: Theme.of(context).textTheme.titleLarge),
        const SizedBox(height: SokoniDimens.space8),
        Text(
          l10n.createSheetStartSellingMessage,
          textAlign: TextAlign.center,
          style: Theme.of(context).textTheme.bodyMedium,
        ),
        const SizedBox(height: SokoniDimens.space20),
        FilledButton(
          onPressed: () {
            Navigator.of(context).pop();
            context.push(SokoniRoutes.sellerOnboarding);
          },
          child: Text(l10n.createSheetStartSellingTitle),
        ),
      ],
    );
  }
}
