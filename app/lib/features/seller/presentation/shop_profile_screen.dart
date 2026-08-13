import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:share_plus/share_plus.dart';

import '../../../core/l10n/gen/app_localizations.dart';
import '../../../core/network/api_exception.dart';
import '../../../core/router/routes.dart';
import '../../../core/theme/colors.dart';
import '../../../core/theme/dimens.dart';
import '../../../data/models/seller_profile.dart';
import '../../../shared/widgets/empty_state.dart';
import '../../../shared/widgets/error_state.dart';
import '../../../shared/widgets/product_card.dart';
import '../../../shared/widgets/report_sheet.dart';
import '../../discovery/providers/discovery_providers.dart';
import '../providers/seller_providers.dart';
import 'widgets/review_distribution_bar.dart';
import 'widgets/shop_media_grid.dart';

/// Shop profile: shop info, media grid tab (with play badges), review
/// distribution + list (CLAUDE.md feature 5). Deep-linkable at
/// `sokoni.co.tz/@handle` — see docs/DEPLOY.md for the web-fallback side of
/// that; in-app this route is `/shop/:handle`.
class ShopProfileScreen extends ConsumerWidget {
  const ShopProfileScreen({required this.handle, super.key});

  final String handle;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = AppLocalizations.of(context);
    final sellerAsync = ref.watch(sellerProfileProvider(handle));

    return Scaffold(
      body: sellerAsync.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (error, _) => SokoniErrorState(
          message: error is ApiException ? error.message : l10n.feedErrorBody,
          onRetry: () => ref.invalidate(sellerProfileProvider(handle)),
        ),
        data: (seller) => DefaultTabController(
          length: 3,
          child: NestedScrollView(
            headerSliverBuilder: (context, innerBoxIsScrolled) => [
              SliverAppBar(
                pinned: true,
                expandedHeight: 200,
                actions: [
                  IconButton(
                    icon: const Icon(Icons.ios_share_rounded),
                    onPressed: () => SharePlus.instance.share(
                      ShareParams(text: 'https://sokoni.co.tz/@${seller.handle}'),
                    ),
                  ),
                  IconButton(
                    icon: const Icon(Icons.flag_outlined),
                    onPressed: () => showReportSheet(
                      context,
                      ref,
                      reportableType: 'shop',
                      reportableId: seller.id,
                    ),
                  ),
                ],
                flexibleSpace: FlexibleSpaceBar(
                  background: Container(
                    color: SokoniColors.surfaceAlt,
                    padding: const EdgeInsets.fromLTRB(
                      SokoniDimens.space16,
                      64,
                      SokoniDimens.space16,
                      SokoniDimens.space16,
                    ),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      mainAxisAlignment: MainAxisAlignment.end,
                      children: [
                        Row(
                          children: [
                            if (seller.isVerified)
                              const Padding(
                                padding: EdgeInsets.only(right: 4),
                                child: Icon(Icons.verified_rounded, color: SokoniColors.sokoniYellow),
                              ),
                            Expanded(
                              child: Text(seller.shopName, style: Theme.of(context).textTheme.displayLarge?.copyWith(fontSize: 24)),
                            ),
                          ],
                        ),
                        Text('@${seller.handle}'),
                        const SizedBox(height: SokoniDimens.space8),
                        Row(
                          children: [
                            const Icon(Icons.star_rounded, size: 16, color: SokoniColors.sokoniYellow),
                            const SizedBox(width: 4),
                            Text('${seller.ratingAvg.toStringAsFixed(1)} (${seller.ratingCount})'),
                            const SizedBox(width: SokoniDimens.space12),
                            Text(
                              seller.isVerified ? l10n.shopVerifiedBadge : l10n.shopPendingBadge,
                              style: Theme.of(context).textTheme.bodySmall,
                            ),
                          ],
                        ),
                      ],
                    ),
                  ),
                ),
                bottom: TabBar(
                  tabs: [
                    Tab(text: l10n.shopProductsTitle),
                    Tab(text: l10n.shopMediaTitle),
                    Tab(text: l10n.shopReviewsTitle),
                  ],
                ),
              ),
            ],
            body: TabBarView(
              children: [
                _ShopProductsTab(sellerId: seller.id),
                ShopMediaGrid(sellerId: seller.id),
                _ShopReviewsTab(handle: handle),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class _ShopProductsTab extends ConsumerWidget {
  const _ShopProductsTab({required this.sellerId});

  final int sellerId;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = AppLocalizations.of(context);
    final productsAsync = ref.watch(
      _shopProductsProvider(sellerId),
    );

    return productsAsync.when(
      loading: () => const Center(child: CircularProgressIndicator()),
      error: (error, _) => SokoniErrorState(
        message: error is ApiException ? error.message : l10n.feedErrorBody,
        onRetry: () => ref.invalidate(_shopProductsProvider(sellerId)),
      ),
      data: (products) => products.isEmpty
          ? SokoniEmptyState(
              icon: Icons.storefront_outlined,
              title: l10n.feedEmptyTitle,
              message: l10n.feedEmptyBody,
            )
          : GridView.builder(
              padding: const EdgeInsets.all(SokoniDimens.space16),
              gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                crossAxisCount: 2,
                mainAxisSpacing: SokoniDimens.space12,
                crossAxisSpacing: SokoniDimens.space12,
                childAspectRatio: 0.62,
              ),
              itemCount: products.length,
              itemBuilder: (context, index) => ProductCard(
                product: products[index],
                onTap: () => context.push(SokoniRoutes.product(products[index].id)),
              ),
            ),
    );
  }
}

final _shopProductsProvider = FutureProvider.family((ref, int sellerId) async {
  final result = await ref
      .watch(productRepositoryProvider)
      .products(sellerId: sellerId, sort: 'newest');
  return result.items;
});

class _ShopReviewsTab extends ConsumerWidget {
  const _ShopReviewsTab({required this.handle});

  final String handle;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = AppLocalizations.of(context);
    final reviewsAsync = ref.watch(sellerReviewsProvider(handle));

    return reviewsAsync.when(
      loading: () => const Center(child: CircularProgressIndicator()),
      error: (error, _) => SokoniErrorState(
        message: error is ApiException ? error.message : l10n.feedErrorBody,
        onRetry: () => ref.invalidate(sellerReviewsProvider(handle)),
      ),
      data: (data) {
        final (page, distribution) = data;
        if (page.items.isEmpty) {
          return SokoniEmptyState(icon: Icons.star_border_rounded, title: l10n.shopNoReviews, message: '');
        }
        return ListView(
          padding: const EdgeInsets.all(SokoniDimens.space16),
          children: [
            ReviewDistributionBar(distribution: distribution, total: page.total),
            const SizedBox(height: SokoniDimens.space16),
            for (final review in page.items) _ReviewTile(review: review),
          ],
        );
      },
    );
  }
}

class _ReviewTile extends StatelessWidget {
  const _ReviewTile({required this.review});

  final dynamic review;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: SokoniDimens.space16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Text(review.buyer?.name ?? '', style: Theme.of(context).textTheme.bodyMedium?.copyWith(fontWeight: FontWeight.w600)),
              const SizedBox(width: SokoniDimens.space8),
              Row(
                children: List.generate(
                  5,
                  (i) => Icon(
                    i < review.rating ? Icons.star_rounded : Icons.star_border_rounded,
                    size: 14,
                    color: SokoniColors.sokoniYellow,
                  ),
                ),
              ),
            ],
          ),
          if (review.comment != null) ...[
            const SizedBox(height: 4),
            Text(review.comment),
          ],
          if (review.reply != null) ...[
            const SizedBox(height: 8),
            Container(
              padding: const EdgeInsets.all(SokoniDimens.space8),
              decoration: BoxDecoration(
                color: SokoniColors.surfaceAlt,
                borderRadius: BorderRadius.circular(SokoniDimens.radiusField),
              ),
              child: Text(review.reply, style: Theme.of(context).textTheme.bodySmall),
            ),
          ],
        ],
      ),
    );
  }
}
