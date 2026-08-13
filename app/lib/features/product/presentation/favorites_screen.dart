import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/l10n/gen/app_localizations.dart';
import '../../../core/network/api_exception.dart';
import '../../../core/router/routes.dart';
import '../../../core/theme/dimens.dart';
import '../../../shared/widgets/empty_state.dart';
import '../../../shared/widgets/error_state.dart';
import '../../../shared/widgets/product_card.dart';
import '../providers/favorites_screen_provider.dart';

class FavoritesScreen extends ConsumerWidget {
  const FavoritesScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = AppLocalizations.of(context);
    final favoritesAsync = ref.watch(favoritesListProvider);

    return Scaffold(
      appBar: AppBar(title: Text(l10n.favoritesTitle)),
      body: favoritesAsync.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (error, _) => SokoniErrorState(
          message: error is ApiException ? error.message : l10n.feedErrorBody,
          onRetry: () => ref.invalidate(favoritesListProvider),
        ),
        data: (items) => items.isEmpty
            ? SokoniEmptyState(
                icon: Icons.favorite_border_rounded,
                title: l10n.favoritesEmptyTitle,
                message: l10n.favoritesEmptyBody,
              )
            : GridView.builder(
                padding: const EdgeInsets.all(SokoniDimens.space16),
                gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                  crossAxisCount: 2,
                  mainAxisSpacing: SokoniDimens.space12,
                  crossAxisSpacing: SokoniDimens.space12,
                  childAspectRatio: 0.62,
                ),
                itemCount: items.length,
                itemBuilder: (context, index) => ProductCard(
                  product: items[index],
                  onTap: () => context.push(SokoniRoutes.product(items[index].id)),
                ),
              ),
      ),
    );
  }
}
