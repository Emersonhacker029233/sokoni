import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../../core/l10n/gen/app_localizations.dart';
import '../../../../core/router/routes.dart';
import '../../../../core/theme/colors.dart';
import '../../../../core/theme/dimens.dart';
import '../../../../core/utils/formatters.dart';
import '../../../../data/models/offer.dart';
import '../../providers/social_providers.dart';
import '../../../../shared/widgets/sokoni_network_image.dart';

/// Offers row in discovery (CLAUDE.md Part 3) — countdown on the card,
/// strikethrough price. Renders nothing when there are no live Offers, same
/// "supplementary content" reasoning as [UpdatesTray].
class OffersRow extends ConsumerWidget {
  const OffersRow({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = AppLocalizations.of(context);
    final offersAsync = ref.watch(offersRowProvider);
    final offers = offersAsync.value?.items ?? const <Offer>[];
    if (offers.isEmpty) return const SizedBox.shrink();

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Padding(
          padding: const EdgeInsets.symmetric(horizontal: SokoniDimens.space16),
          child: Text(l10n.offersRowTitle, style: Theme.of(context).textTheme.titleMedium),
        ),
        const SizedBox(height: SokoniDimens.space8),
        SizedBox(
          height: 200,
          child: ListView.separated(
            scrollDirection: Axis.horizontal,
            padding: const EdgeInsets.symmetric(horizontal: SokoniDimens.space16),
            itemCount: offers.length,
            separatorBuilder: (context, index) => const SizedBox(width: SokoniDimens.space12),
            itemBuilder: (context, index) => _OfferCard(offer: offers[index]),
          ),
        ),
      ],
    );
  }
}

class _OfferCard extends StatelessWidget {
  const _OfferCard({required this.offer});

  final Offer offer;

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final outline = isDark ? SokoniColors.darkOutline : SokoniColors.outline;
    final surfaceAlt = isDark ? SokoniColors.darkSurfaceAlt : SokoniColors.surfaceAlt;
    final product = offer.product;
    final remaining = offer.timeRemaining;
    final countdown = remaining.inHours >= 24
        ? l10n.offerCountdownDays(remaining.inDays)
        : l10n.offerCountdownHours(remaining.inHours.clamp(0, 23));

    return GestureDetector(
      onTap: product == null ? null : () => context.push(SokoniRoutes.product(product.id)),
      child: Container(
        width: 150,
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(SokoniDimens.radiusCard),
          border: Border.all(color: outline),
        ),
        clipBehavior: Clip.antiAlias,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Stack(
              children: [
                AspectRatio(
                  aspectRatio: 1,
                  child: product?.coverImageUrl != null
                      ? SokoniNetworkImage(imageUrl: product!.coverImageUrl!, fit: BoxFit.cover)
                      : Container(color: surfaceAlt, child: const Icon(Icons.image_outlined)),
                ),
                Positioned(
                  left: SokoniDimens.space8,
                  top: SokoniDimens.space8,
                  child: Container(
                    padding: const EdgeInsets.symmetric(horizontal: SokoniDimens.space8, vertical: 2),
                    decoration: BoxDecoration(
                      color: SokoniColors.sokoniYellow,
                      borderRadius: BorderRadius.circular(SokoniDimens.radiusChip),
                    ),
                    child: Text(
                      countdown,
                      style: const TextStyle(color: SokoniColors.onYellow, fontSize: 11, fontWeight: FontWeight.w700),
                    ),
                  ),
                ),
              ],
            ),
            Padding(
              padding: const EdgeInsets.all(SokoniDimens.space8),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    product?.title ?? '',
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: Theme.of(context).textTheme.bodySmall,
                  ),
                  const SizedBox(height: SokoniDimens.space4),
                  Text(
                    SokoniFormat.tzs(offer.priceSnapshot.round()),
                    style: const TextStyle(
                      decoration: TextDecoration.lineThrough,
                      fontSize: 11,
                      color: SokoniColors.danger,
                    ),
                  ),
                  Text(
                    SokoniFormat.tzs(offer.discountedPrice),
                    style: Theme.of(context).textTheme.bodyMedium?.copyWith(fontWeight: FontWeight.w700),
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
