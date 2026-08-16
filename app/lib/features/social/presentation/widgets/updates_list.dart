import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../../core/l10n/gen/app_localizations.dart';
import '../../../../core/motion/page_transitions.dart';
import '../../../../core/theme/dimens.dart';
import '../../../../shared/widgets/empty_state.dart';
import '../../../../shared/widgets/error_state.dart';
import '../../providers/social_providers.dart';
import '../story_viewer_screen.dart';

/// One seller's own active Updates (CLAUDE.md Part 3), the shop profile's
/// Updates tab — a plain list (this is the shop's own page, not a
/// multi-shop tray, so no ring/grouping needed), tapping one opens the
/// same full-screen story viewer as the home feed's tray.
class UpdatesList extends ConsumerWidget {
  const UpdatesList({required this.sellerId, super.key});

  final int sellerId;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = AppLocalizations.of(context);
    final updatesAsync = ref.watch(sellerUpdatesProvider(sellerId));

    return updatesAsync.when(
      loading: () => const Center(child: CircularProgressIndicator()),
      error: (error, _) => SokoniErrorState(
        message: '$error',
        onRetry: () => ref.invalidate(sellerUpdatesProvider(sellerId)),
      ),
      data: (updates) {
        if (updates.isEmpty) {
          return SokoniEmptyState(
            icon: Icons.auto_stories_outlined,
            title: l10n.updatesEmptyTitle,
            message: l10n.updatesEmptyMessage,
          );
        }
        return ListView.separated(
          padding: const EdgeInsets.all(SokoniDimens.space16),
          itemCount: updates.length,
          separatorBuilder: (context, index) => const SizedBox(height: SokoniDimens.space8),
          itemBuilder: (context, index) {
            final update = updates[index];
            return ListTile(
              contentPadding: EdgeInsets.zero,
              leading: ClipRRect(
                borderRadius: BorderRadius.circular(SokoniDimens.radiusChip),
                child: SizedBox(
                  width: 48,
                  height: 48,
                  child: CachedNetworkImage(imageUrl: update.thumbPath ?? update.mediaPath, fit: BoxFit.cover),
                ),
              ),
              title: Text(update.caption ?? '', maxLines: 1, overflow: TextOverflow.ellipsis),
              onTap: () => Navigator.of(context).push(
                SharedAxisZPageRoute<void>(
                  child: StoryViewerScreen(updates: [update], startIndex: 0),
                ),
              ),
            );
          },
        );
      },
    );
  }
}
