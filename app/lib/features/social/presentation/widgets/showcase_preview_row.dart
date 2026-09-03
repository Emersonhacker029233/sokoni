import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../../core/l10n/gen/app_localizations.dart';
import '../../../../core/router/routes.dart';
import '../../../../core/theme/colors.dart';
import '../../../../core/theme/dimens.dart';
import '../../../../data/models/showcase.dart';
import '../../providers/social_providers.dart';
import '../../../../shared/widgets/sokoni_network_image.dart';

/// Small entry point into the full-screen Showcase feed (CLAUDE.md Part
/// 3), a row of thumbnails on the home feed rather than a sixth bottom-nav
/// tab — the nav stays the five tabs CLAUDE.md fixed in Phase 3 (see
/// DECISIONS.md). Tapping any thumbnail opens the same full vertical feed;
/// renders nothing when there's nothing to showcase yet.
class ShowcasePreviewRow extends ConsumerWidget {
  const ShowcasePreviewRow({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = AppLocalizations.of(context);
    final feedAsync = ref.watch(showcaseFeedProvider);
    final items = feedAsync.value?.items ?? const <Showcase>[];
    if (items.isEmpty) return const SizedBox.shrink();

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Padding(
          padding: const EdgeInsets.symmetric(horizontal: SokoniDimens.space16),
          child: Text(l10n.showcaseRowTitle, style: Theme.of(context).textTheme.titleMedium),
        ),
        const SizedBox(height: SokoniDimens.space8),
        SizedBox(
          height: 130,
          child: ListView.separated(
            scrollDirection: Axis.horizontal,
            padding: const EdgeInsets.symmetric(horizontal: SokoniDimens.space16),
            itemCount: items.length,
            separatorBuilder: (context, index) => const SizedBox(width: SokoniDimens.space8),
            itemBuilder: (context, index) {
              final showcase = items[index];
              return GestureDetector(
                onTap: () => context.push(SokoniRoutes.showcaseFeed),
                child: ClipRRect(
                  borderRadius: BorderRadius.circular(SokoniDimens.radiusCard),
                  child: Stack(
                    fit: StackFit.expand,
                    children: [
                      SizedBox(
                        width: 80,
                        child: SokoniNetworkImage(imageUrl: showcase.thumbPath, fit: BoxFit.cover),
                      ),
                      const Positioned(
                        right: 6,
                        bottom: 6,
                        child: Icon(Icons.play_circle_fill_rounded, color: SokoniColors.surface, size: 20),
                      ),
                    ],
                  ),
                ),
              );
            },
          ),
        ),
      ],
    );
  }
}
