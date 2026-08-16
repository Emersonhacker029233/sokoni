import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_riverpod/legacy.dart';

import '../../../core/providers.dart';
import '../../../data/api/feed_api.dart';
import '../../../data/models/comment.dart';
import '../../../data/models/feed_item.dart';
import '../../../data/repositories/feed_repository.dart';
import 'discovery_location.dart';

final feedApiProvider = Provider<FeedApi>((ref) {
  return FeedApi(ref.watch(dioProvider));
});

final feedRepositoryProvider = Provider<FeedRepository>((ref) {
  return FeedRepository(api: ref.watch(feedApiProvider));
});

class ForYouFeedState {
  const ForYouFeedState({required this.items, required this.hasMore, required this.page});

  final List<FeedItem> items;
  final bool hasMore;
  final int page;
}

/// Drives the "For You" tab — same shape as `DiscoveryFeedController`
/// (refresh/loadMore), just over the composed feed endpoint instead of the
/// plain product grid.
class ForYouFeedController extends AsyncNotifier<ForYouFeedState> {
  @override
  Future<ForYouFeedState> build() async {
    final location = await ref.watch(discoveryLocationProvider.future);
    final result = await ref
        .watch(feedRepositoryProvider)
        .feed(lat: location.coords?.lat, lng: location.coords?.lng, page: 1);
    return ForYouFeedState(items: result.items, hasMore: result.hasMore, page: result.currentPage);
  }

  Future<void> refresh() async {
    ref.invalidateSelf();
    await future;
  }

  Future<void> loadMore() async {
    final current = state.value;
    if (current == null || !current.hasMore) return;

    final location = await ref.read(discoveryLocationProvider.future);
    final result = await ref
        .read(feedRepositoryProvider)
        .feed(lat: location.coords?.lat, lng: location.coords?.lng, page: current.page + 1);

    state = AsyncData(
      ForYouFeedState(
        items: [...current.items, ...result.items],
        hasMore: result.hasMore,
        page: result.currentPage,
      ),
    );
  }
}

final forYouFeedProvider = AsyncNotifierProvider<ForYouFeedController, ForYouFeedState>(ForYouFeedController.new);

/// Home's two tabs (CLAUDE.md Part 3): "For You" (the composed feed,
/// default) and "Shops" (the existing category/sort-filtered grid).
enum HomeTab { forYou, shops }

final homeTabProvider = StateProvider<HomeTab>((ref) => HomeTab.forYou);

class CommentThreadState {
  const CommentThreadState({required this.items, required this.hasMore, required this.page});

  final List<ProductComment> items;
  final bool hasMore;
  final int page;
}

/// One product's comment thread — family-keyed by product id, constructor
/// carries the arg (this API's non-generator family-notifier shape; see
/// `ChatThreadController`'s docblock for the same pattern).
class CommentThreadController extends AsyncNotifier<CommentThreadState> {
  CommentThreadController(this.productId);

  final int productId;

  @override
  Future<CommentThreadState> build() async {
    final result = await ref.watch(feedRepositoryProvider).comments(productId, page: 1);
    return CommentThreadState(items: result.items, hasMore: result.hasMore, page: result.currentPage);
  }

  Future<void> loadMore() async {
    final current = state.value;
    if (current == null || !current.hasMore) return;

    final result = await ref.read(feedRepositoryProvider).comments(productId, page: current.page + 1);
    state = AsyncData(
      CommentThreadState(
        items: [...current.items, ...result.items],
        hasMore: result.hasMore,
        page: result.currentPage,
      ),
    );
  }

  Future<void> post({required String body, int? parentId}) async {
    final comment = await ref.read(feedRepositoryProvider).postComment(productId, body: body, parentId: parentId);
    final current = state.value;
    if (current == null) return;

    if (parentId == null) {
      state = AsyncData(
        CommentThreadState(items: [comment, ...current.items], hasMore: current.hasMore, page: current.page),
      );
    } else {
      state = AsyncData(
        CommentThreadState(
          items: [
            for (final c in current.items)
              if (c.id == parentId) c.copyWith(replies: [...c.replies, comment]) else c,
          ],
          hasMore: current.hasMore,
          page: current.page,
        ),
      );
    }
  }
}

final commentThreadProvider = AsyncNotifierProvider.autoDispose.family<CommentThreadController, CommentThreadState, int>(
  CommentThreadController.new,
);
