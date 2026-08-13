import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:share_plus/share_plus.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../../core/l10n/gen/app_localizations.dart';
import '../../../core/network/api_exception.dart';
import '../../../core/router/routes.dart';
import '../../../core/theme/colors.dart';
import '../../../core/theme/dimens.dart';
import '../../../core/utils/formatters.dart';
import '../../../data/models/product.dart';
import '../../../shared/widgets/error_state.dart';
import '../../../shared/widgets/report_sheet.dart';
import '../../seller/providers/seller_providers.dart';
import '../providers/favorites_providers.dart';
import '../providers/product_detail_providers.dart';
import 'widgets/product_media_carousel.dart';

class ProductDetailScreen extends ConsumerWidget {
  const ProductDetailScreen({required this.productId, super.key});

  final int productId;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = AppLocalizations.of(context);
    final productAsync = ref.watch(productDetailProvider(productId));

    return Scaffold(
      body: productAsync.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (error, _) => SokoniErrorState(
          message: error is ApiException ? error.message : l10n.feedErrorBody,
          onRetry: () => ref.invalidate(productDetailProvider(productId)),
        ),
        data: (product) => _ProductDetailBody(product: product),
      ),
    );
  }
}

class _ProductDetailBody extends ConsumerWidget {
  const _ProductDetailBody({required this.product});

  final Product product;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = AppLocalizations.of(context);
    final textTheme = Theme.of(context).textTheme;
    final favorited = isProductFavorited(ref, product);

    return CustomScrollView(
      slivers: [
        SliverAppBar(
          pinned: true,
          expandedHeight: 340,
          flexibleSpace: FlexibleSpaceBar(
            background: ProductMediaCarousel(
              media: product.media,
              heroTag: 'product-${product.id}',
            ),
          ),
          actions: [
            IconButton(
              icon: Icon(favorited ? Icons.favorite_rounded : Icons.favorite_border_rounded),
              color: favorited ? SokoniColors.danger : null,
              onPressed: () => toggleProductFavorite(ref, product),
            ),
            IconButton(
              icon: const Icon(Icons.ios_share_rounded),
              onPressed: () => SharePlus.instance.share(
                ShareParams(
                  text: '${product.title} — ${SokoniFormat.tzs(product.price)}\n'
                      'https://sokoni.co.tz/products/${product.id}',
                ),
              ),
            ),
            IconButton(
              icon: const Icon(Icons.flag_outlined),
              onPressed: () => showReportSheet(
                context,
                ref,
                reportableType: 'product',
                reportableId: product.id,
              ),
            ),
          ],
        ),
        SliverPadding(
          padding: const EdgeInsets.all(SokoniDimens.space16),
          sliver: SliverList.list(
            children: [
              Text(product.title, style: textTheme.titleLarge),
              const SizedBox(height: SokoniDimens.space8),
              Text(
                SokoniFormat.tzs(product.price),
                style: textTheme.titleLarge?.copyWith(color: SokoniColors.sokoniBlack, fontWeight: FontWeight.w800),
              ),
              const SizedBox(height: SokoniDimens.space4),
              Text(
                product.condition == 'new' ? l10n.productConditionNew : l10n.productConditionUsed,
                style: textTheme.bodySmall,
              ),
              Text(l10n.productStock(product.stock), style: textTheme.bodySmall),
              const SizedBox(height: SokoniDimens.space16),
              if (product.seller != null) _SellerCard(product: product),
              const SizedBox(height: SokoniDimens.space16),
              if (product.description != null && product.description!.isNotEmpty) ...[
                Text(product.description!, style: textTheme.bodyMedium),
                const SizedBox(height: SokoniDimens.space16),
              ],
              _ActionButtons(product: product),
            ],
          ),
        ),
      ],
    );
  }
}

class _SellerCard extends StatelessWidget {
  const _SellerCard({required this.product});

  final Product product;

  @override
  Widget build(BuildContext context) {
    final seller = product.seller!;
    return Card(
      child: ListTile(
        onTap: () => context.push(SokoniRoutes.shop(seller.handle)),
        title: Row(
          children: [
            if (seller.isVerified)
              const Padding(
                padding: EdgeInsets.only(right: 4),
                child: Icon(Icons.verified_rounded, size: 16, color: SokoniColors.sokoniYellow),
              ),
            Expanded(child: Text(seller.shopName)),
          ],
        ),
        subtitle: Row(
          children: [
            const Icon(Icons.star_rounded, size: 14, color: SokoniColors.sokoniYellow),
            const SizedBox(width: 2),
            Text('${seller.ratingAvg.toStringAsFixed(1)} (${seller.ratingCount})'),
          ],
        ),
        trailing: const Icon(Icons.chevron_right_rounded),
      ),
    );
  }
}

class _ActionButtons extends ConsumerWidget {
  const _ActionButtons({required this.product});

  final Product product;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = AppLocalizations.of(context);

    return Column(
      children: [
        FilledButton.icon(
          onPressed: () async {
            final conversationId = await ref
                .read(sellerRepositoryProvider)
                .startConversation(sellerId: product.seller!.id, productId: product.id);
            if (context.mounted) {
              await context.push(SokoniRoutes.conversation(conversationId));
            }
          },
          icon: const Icon(Icons.chat_bubble_outline_rounded),
          label: Text(l10n.productMessageSeller),
        ),
        if (product.seller?.whatsapp != null) ...[
          const SizedBox(height: SokoniDimens.space8),
          OutlinedButton.icon(
            onPressed: () {
              // wa.me wants the number digits-only (no leading '+').
              final digits = product.seller!.whatsapp!.replaceAll('+', '');
              final text = Uri.encodeComponent(
                '${product.title} — https://sokoni.co.tz/products/${product.id}',
              );
              launchUrl(
                Uri.parse('https://wa.me/$digits?text=$text'),
                mode: LaunchMode.externalApplication,
              );
            },
            icon: const Icon(Icons.chat_rounded),
            label: Text(l10n.productWhatsapp),
          ),
        ],
      ],
    );
  }
}
