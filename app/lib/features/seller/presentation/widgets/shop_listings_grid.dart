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
import '../../../../shared/widgets/bottom_gradient_scrim.dart';
import '../../../../shared/widgets/empty_state.dart';
import '../../../../shared/widgets/error_state.dart';
import '../../../discovery/providers/discovery_providers.dart';

/// The shop profile's Listings tab (CLAUDE.md Part 4): a 3-column grid,
/// one tile per Listing (not per media item — that was the old, now
/// dropped, generic "Media" tab; Part 3 already gave Updates/Showcases
/// their own dedicated tabs, which made an all-media-mixed-together grid
/// redundant), with a play badge on video covers and a multi-image badge
/// on carousels.
class ShopListingsGrid extends ConsumerWidget {
  const ShopListingsGrid({required this.sellerId, super.key});

  final int sellerId;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = AppLocalizations.of(context);
    final productsAsync = ref.watch(_shopListingsProvider(sellerId));

    return productsAsync.when(
      loading: () => const Center(child: CircularProgressIndicator()),
      error: (error, _) => SokoniErrorState(
        message: error is ApiException ? error.message : l10n.feedErrorBody,
        onRetry: () => ref.invalidate(_shopListingsProvider(sellerId)),
      ),
      data: (products) => products.isEmpty
          ? SokoniEmptyState(icon: Icons.grid_on_rounded, title: l10n.feedEmptyTitle, message: l10n.feedEmptyBody)
          : GridView.builder(
              padding: const EdgeInsets.all(SokoniDimens.space4),
              gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                crossAxisCount: 3,
                mainAxisSpacing: SokoniDimens.space4,
                crossAxisSpacing: SokoniDimens.space4,
                childAspectRatio: 1,
              ),
              itemCount: products.length,
              itemBuilder: (context, index) => _ListingTile(product: products[index]),
            ),
    );
  }
}

class _ListingTile extends StatelessWidget {
  const _ListingTile({required this.product});

  final Product product;

  @override
  Widget build(BuildContext context) {
    final cover = product.media.isEmpty ? null : product.media.first;

    return GestureDetector(
      onTap: () => context.push(SokoniRoutes.product(product.id)),
      child: Stack(
        fit: StackFit.expand,
        children: [
          cover == null
              ? Container(color: SokoniColors.surfaceAlt, child: const Icon(Icons.image_outlined))
              : CachedNetworkImage(
                  imageUrl: cover.cardPath ?? cover.thumbPath ?? cover.path,
                  fit: BoxFit.cover,
                  placeholder: (context, url) => Container(color: SokoniColors.surfaceAlt),
                ),
          if (cover?.isVideo ?? false) ...[
            const BottomGradientScrim(heightFraction: 0.35),
            const Positioned(
              right: 4,
              bottom: 4,
              child: Icon(Icons.play_circle_fill_rounded, color: Colors.white, size: 20),
            ),
          ] else if (product.media.length > 1)
            const Positioned(
              right: 4,
              top: 4,
              child: Icon(Icons.filter_none_rounded, color: Colors.white, size: 16, shadows: [Shadow(blurRadius: 3)]),
            ),
        ],
      ),
    );
  }
}

final _shopListingsProvider = FutureProvider.family((ref, int sellerId) async {
  final result = await ref.watch(productRepositoryProvider).products(sellerId: sellerId, sort: 'newest');
  return result.items;
});
