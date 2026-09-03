import 'package:flutter/material.dart';

import '../../core/motion/hero_image_transition.dart';
import '../../core/theme/colors.dart';
import '../../core/theme/dimens.dart';
import '../../core/utils/formatters.dart';
import '../../data/models/product.dart';
import 'sokoni_network_image.dart';

/// The product card used across the discovery feed, search results and
/// favourites — distance, TZS price, seller rating, verified tick (CLAUDE.md
/// feature 1). CLAUDE.md Part 5's polish pass: 12dp corners on the image
/// only (not the whole card — a lighter look than a fully-rounded outline),
/// a tighter text block, price as the strongest element after the image,
/// and rating+distance collapsed into one muted caption line instead of
/// two separately-styled ones competing for attention.
class ProductCard extends StatelessWidget {
  const ProductCard({required this.product, required this.onTap, super.key});

  final Product product;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final outline = isDark ? SokoniColors.darkOutline : SokoniColors.outline;
    final surfaceAlt = isDark ? SokoniColors.darkSurfaceAlt : SokoniColors.surfaceAlt;
    final textTheme = Theme.of(context).textTheme;
    final seller = product.seller;
    final captionParts = [
      if (seller != null) seller.ratingAvg.toStringAsFixed(1),
      if (product.distanceKm != null) SokoniFormat.distanceKm(product.distanceKm!),
    ];

    return GestureDetector(
      onTap: onTap,
      child: Container(
        decoration: BoxDecoration(
          border: Border.all(color: outline),
          // Top corners only — the card's outline follows the same
          // silhouette as the image it wraps, rather than the image being
          // top-rounded inside a fully-rounded card border.
          borderRadius: const BorderRadius.only(
            topLeft: Radius.circular(SokoniDimens.radiusChip),
            topRight: Radius.circular(SokoniDimens.radiusChip),
          ),
        ),
        clipBehavior: Clip.antiAlias,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            AspectRatio(
              aspectRatio: 1,
              child: SokoniHeroImage(
                tag: 'product-${product.id}',
                borderRadius: 0,
                child: product.coverImageUrl != null
                    ? SokoniNetworkImage(
                        imageUrl: product.coverImageUrl!,
                        fit: BoxFit.cover,
                        placeholder: (context, url) => Container(color: surfaceAlt),
                        errorWidget: (context, url, error) => Container(
                          color: surfaceAlt,
                          child: const Icon(Icons.image_not_supported_outlined),
                        ),
                      )
                    : Container(
                        color: surfaceAlt,
                        child: const Icon(Icons.image_outlined),
                      ),
              ),
            ),
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: SokoniDimens.space8, vertical: SokoniDimens.space4),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  // Price first and boldest — the strongest element on the
                  // card after the image, ahead of the title.
                  Text(
                    SokoniFormat.tzs(product.price),
                    style: textTheme.titleSmall?.copyWith(fontWeight: FontWeight.w700),
                  ),
                  Text(
                    product.title,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: textTheme.bodySmall,
                  ),
                  if (seller?.isVerified == true || captionParts.isNotEmpty)
                    Padding(
                      padding: const EdgeInsets.only(top: 2),
                      child: Row(
                        children: [
                          if (seller?.isVerified == true)
                            const Padding(
                              padding: EdgeInsets.only(right: 2),
                              child: Icon(Icons.verified_rounded, size: 12, color: SokoniColors.sokoniYellow),
                            ),
                          if (captionParts.isNotEmpty)
                            Expanded(
                              child: Text(
                                captionParts.join(' · '),
                                maxLines: 1,
                                overflow: TextOverflow.ellipsis,
                                style: textTheme.bodySmall?.copyWith(color: Theme.of(context).hintColor),
                              ),
                            ),
                        ],
                      ),
                    ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}
