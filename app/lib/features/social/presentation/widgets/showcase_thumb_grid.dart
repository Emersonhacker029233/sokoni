import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../../core/l10n/gen/app_localizations.dart';
import '../../../../core/router/routes.dart';
import '../../../../core/theme/dimens.dart';
import '../../../../shared/widgets/bottom_gradient_scrim.dart';
import '../../../../shared/widgets/empty_state.dart';
import '../../../../shared/widgets/error_state.dart';
import '../../providers/social_providers.dart';
import '../../../../shared/widgets/sokoni_network_image.dart';

/// One seller's own Showcases (CLAUDE.md Part 3), the shop profile's
/// Showcase tab — same 3-column grid shape as `ShopListingsGrid`, plus a
/// view count (CLAUDE.md Part 4: "view counts on video tiles"); tapping a
/// tile opens the global full-screen vertical feed.
class ShowcaseThumbGrid extends ConsumerWidget {
  const ShowcaseThumbGrid({required this.sellerId, super.key});

  final int sellerId;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = AppLocalizations.of(context);
    final showcasesAsync = ref.watch(sellerShowcasesProvider(sellerId));

    return showcasesAsync.when(
      loading: () => const Center(child: CircularProgressIndicator()),
      error: (error, _) => SokoniErrorState(
        message: '$error',
        onRetry: () => ref.invalidate(sellerShowcasesProvider(sellerId)),
      ),
      data: (showcases) {
        if (showcases.isEmpty) {
          return SokoniEmptyState(
            icon: Icons.movie_creation_outlined,
            title: l10n.showcaseEmptyTitle,
            message: l10n.showcaseEmptyMessage,
          );
        }
        return GridView.builder(
          padding: const EdgeInsets.all(SokoniDimens.space4),
          gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
            crossAxisCount: 3,
            mainAxisSpacing: SokoniDimens.space4,
            crossAxisSpacing: SokoniDimens.space4,
          ),
          itemCount: showcases.length,
          itemBuilder: (context, index) {
            final showcase = showcases[index];
            return GestureDetector(
              onTap: () => context.push(SokoniRoutes.showcaseFeed),
              child: Stack(
                fit: StackFit.expand,
                children: [
                  SokoniNetworkImage(imageUrl: showcase.thumbPath, fit: BoxFit.cover),
                  const BottomGradientScrim(heightFraction: 0.3),
                  Positioned(
                    left: 4,
                    bottom: 4,
                    child: Row(
                      children: [
                        const Icon(Icons.play_arrow_rounded, color: Colors.white, size: 16, shadows: [Shadow(blurRadius: 3)]),
                        Text(
                          '${showcase.views}',
                          style: const TextStyle(color: Colors.white, fontSize: 12, fontWeight: FontWeight.w600, shadows: [Shadow(blurRadius: 3)]),
                        ),
                      ],
                    ),
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
