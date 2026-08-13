import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';

import '../../core/motion/hero_image_transition.dart';
import '../../core/theme/colors.dart';
import '../../core/theme/dimens.dart';
import '../../core/utils/formatters.dart';
import '../../data/models/product.dart';

/// The product card used across the discovery feed, search results and
/// favourites — distance, TZS price, seller rating, verified tick (CLAUDE.md
/// feature 1).
class ProductCard extends StatelessWidget {
  const ProductCard({required this.product, required this.onTap, super.key});

  final Product product;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final outline = isDark ? SokoniColors.darkOutline : SokoniColors.outline;
    final textTheme = Theme.of(context).textTheme;

    return GestureDetector(
      onTap: onTap,
      child: Container(
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(SokoniDimens.radiusCard),
          border: Border.all(color: outline),
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
                    ? CachedNetworkImage(
                        imageUrl: product.coverImageUrl!,
                        fit: BoxFit.cover,
                        placeholder: (context, url) => Container(color: SokoniColors.surfaceAlt),
                        errorWidget: (context, url, error) => Container(
                          color: SokoniColors.surfaceAlt,
                          child: const Icon(Icons.image_not_supported_outlined),
                        ),
                      )
                    : Container(
                        color: SokoniColors.surfaceAlt,
                        child: const Icon(Icons.image_outlined),
                      ),
              ),
            ),
            Padding(
              padding: const EdgeInsets.all(SokoniDimens.space8),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    product.title,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: textTheme.bodyMedium?.copyWith(fontWeight: FontWeight.w600),
                  ),
                  const SizedBox(height: SokoniDimens.space4),
                  Text(
                    SokoniFormat.tzs(product.price),
                    style: textTheme.bodyMedium?.copyWith(
                      fontWeight: FontWeight.w700,
                      color: SokoniColors.sokoniBlack,
                    ),
                  ),
                  const SizedBox(height: SokoniDimens.space4),
                  Row(
                    children: [
                      if (product.seller != null) ...[
                        if (product.seller!.isVerified)
                          const Padding(
                            padding: EdgeInsets.only(right: 2),
                            child: Icon(Icons.verified_rounded, size: 13, color: SokoniColors.sokoniYellow),
                          ),
                        const Icon(Icons.star_rounded, size: 13, color: SokoniColors.sokoniYellow),
                        const SizedBox(width: 2),
                        Text(
                          product.seller!.ratingAvg.toStringAsFixed(1),
                          style: textTheme.bodySmall,
                        ),
                      ],
                      if (product.distanceKm != null) ...[
                        const SizedBox(width: SokoniDimens.space8),
                        Expanded(
                          child: Text(
                            SokoniFormat.distanceKm(product.distanceKm!),
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: textTheme.bodySmall,
                          ),
                        ),
                      ],
                    ],
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
