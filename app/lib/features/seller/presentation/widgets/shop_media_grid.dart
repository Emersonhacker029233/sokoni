import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../../core/l10n/gen/app_localizations.dart';
import '../../../../core/network/api_exception.dart';
import '../../../../core/router/routes.dart';
import '../../../../core/theme/colors.dart';
import '../../../../core/theme/dimens.dart';
import '../../../../data/models/product.dart';
import '../../../../data/models/product_media.dart';
import '../../../../shared/widgets/empty_state.dart';
import '../../../../shared/widgets/error_state.dart';
import '../../../discovery/providers/discovery_providers.dart';

/// Mixed image/video grid across every one of a shop's products, with play
/// badges on video tiles (CLAUDE.md feature 7).
class ShopMediaGrid extends ConsumerWidget {
  const ShopMediaGrid({required this.sellerId, super.key});

  final int sellerId;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = AppLocalizations.of(context);
    final productsAsync = ref.watch(_shopMediaProductsProvider(sellerId));

    return productsAsync.when(
      loading: () => const Center(child: CircularProgressIndicator()),
      error: (error, _) => SokoniErrorState(
        message: error is ApiException ? error.message : l10n.feedErrorBody,
        onRetry: () => ref.invalidate(_shopMediaProductsProvider(sellerId)),
      ),
      data: (products) {
        final tiles = <(Product product, int mediaIndex)>[
          for (final product in products)
            for (var i = 0; i < product.media.length; i++) (product, i),
        ];
        if (tiles.isEmpty) {
          return SokoniEmptyState(
            icon: Icons.grid_on_rounded,
            title: l10n.feedEmptyTitle,
            message: l10n.feedEmptyBody,
          );
        }
        return GridView.builder(
          padding: const EdgeInsets.all(SokoniDimens.space4),
          gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
            crossAxisCount: 3,
            mainAxisSpacing: SokoniDimens.space4,
            crossAxisSpacing: SokoniDimens.space4,
          ),
          itemCount: tiles.length,
          itemBuilder: (context, index) {
            final (product, mediaIndex) = tiles[index];
            final media = product.media[mediaIndex];
            return GestureDetector(
              onTap: () => context.push(SokoniRoutes.product(product.id)),
              child: Stack(
                fit: StackFit.expand,
                children: [
                  CachedNetworkImage(
                    imageUrl: media.thumbPath ?? media.path,
                    fit: BoxFit.cover,
                    placeholder: (context, url) => Container(color: SokoniColors.surfaceAlt),
                  ),
                  if (media.isVideo)
                    const Positioned(
                      right: 4,
                      bottom: 4,
                      child: Icon(Icons.play_circle_fill_rounded, color: Colors.white, size: 20),
                    ),
                ],
              ),
            );
          },
        );
      },
    );
  }
}

final _shopMediaProductsProvider = FutureProvider.family((ref, int sellerId) async {
  final result = await ref
      .watch(productRepositoryProvider)
      .products(sellerId: sellerId, sort: 'newest');
  return result.items;
});
