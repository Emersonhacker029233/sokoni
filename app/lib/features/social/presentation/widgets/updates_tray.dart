import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../../core/motion/motion.dart';
import '../../../../core/theme/colors.dart';
import '../../../../core/theme/dimens.dart';
import '../../../../data/models/social_update.dart';
import '../../providers/social_providers.dart';
import '../story_viewer_screen.dart';
import '../../../../shared/widgets/sokoni_network_image.dart';

/// Instagram-stories-style tray across the top of the home feed (CLAUDE.md
/// Part 3), one ring per shop with an active Update, Sokoni yellow while
/// unseen. Renders nothing when there are no active Updates — supplementary
/// content, not a primary feed state, so it doesn't need its own empty
/// illustration (unlike the feed itself).
class UpdatesTray extends ConsumerWidget {
  const UpdatesTray({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final updatesAsync = ref.watch(updatesTrayProvider);
    final seenMarksAsync = ref.watch(updateSeenMarksProvider);

    final updates = updatesAsync.value?.items ?? const <SocialUpdate>[];
    if (updates.isEmpty) return const SizedBox.shrink();

    final bySeller = <int, List<SocialUpdate>>{};
    for (final update in updates) {
      final sellerId = update.seller?.id;
      if (sellerId == null) continue;
      (bySeller[sellerId] ??= []).add(update);
    }
    final sellerIds = bySeller.keys.toList();

    return SizedBox(
      height: 96,
      child: ListView.separated(
        scrollDirection: Axis.horizontal,
        padding: const EdgeInsets.symmetric(horizontal: SokoniDimens.space16, vertical: SokoniDimens.space8),
        itemCount: sellerIds.length,
        separatorBuilder: (context, index) => const SizedBox(width: SokoniDimens.space12),
        itemBuilder: (context, index) {
          final sellerUpdates = bySeller[sellerIds[index]]!;
          final seller = sellerUpdates.first.seller!;
          final latest = sellerUpdates.map((u) => u.createdAt).whereType<DateTime>().fold<DateTime?>(
            null,
            (a, b) => a == null || b.isAfter(a) ? b : a,
          );
          final seen = latest != null &&
              (seenMarksAsync.value?[seller.id] != null) &&
              !latest.isAfter(seenMarksAsync.value![seller.id]!);

          return GestureDetector(
            onTap: () => Navigator.of(context).push(
              SharedAxisZPageRoute<void>(
                child: StoryViewerScreen(updates: sellerUpdates, startIndex: 0),
              ),
            ),
            child: SizedBox(
              width: 64,
              child: Column(
                children: [
                  StoryRing(
                    seen: seen,
                    size: 64,
                    child: SokoniNetworkImage(
                      imageUrl: sellerUpdates.first.thumbPath ?? sellerUpdates.first.mediaPath,
                      fit: BoxFit.cover,
                      errorWidget: (context, url, error) => Container(color: SokoniColors.surfaceAlt),
                    ),
                  ),
                  const SizedBox(height: SokoniDimens.space4),
                  Text(
                    seller.shopName,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: Theme.of(context).textTheme.labelSmall,
                  ),
                ],
              ),
            ),
          );
        },
      ),
    );
  }
}
