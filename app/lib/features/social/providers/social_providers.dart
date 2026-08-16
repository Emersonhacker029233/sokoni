import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_riverpod/legacy.dart';

import '../../../core/providers.dart';
import '../../../data/api/offers_api.dart';
import '../../../data/api/showcases_api.dart';
import '../../../data/api/updates_api.dart';
import '../../../data/models/showcase.dart';
import '../../../data/models/social_update.dart';
import '../../../data/repositories/offers_repository.dart';
import '../../../data/repositories/showcases_repository.dart';
import '../../../data/repositories/updates_repository.dart';

final updatesApiProvider = Provider<UpdatesApi>((ref) => UpdatesApi(ref.watch(dioProvider)));
final offersApiProvider = Provider<OffersApi>((ref) => OffersApi(ref.watch(dioProvider)));
final showcasesApiProvider = Provider<ShowcasesApi>((ref) => ShowcasesApi(ref.watch(dioProvider)));

final updatesRepositoryProvider = Provider<UpdatesRepository>((ref) {
  return UpdatesRepository(api: ref.watch(updatesApiProvider), dio: ref.watch(dioProvider));
});

final offersRepositoryProvider = Provider<OffersRepository>((ref) {
  return OffersRepository(api: ref.watch(offersApiProvider));
});

final showcasesRepositoryProvider = Provider<ShowcasesRepository>((ref) {
  return ShowcasesRepository(api: ref.watch(showcasesApiProvider), dio: ref.watch(dioProvider));
});

/// Shared "Following" filter (CLAUDE.md Part 3) — one toggle read by the
/// Updates tray, Offers row and Showcase feed alike, so switching it in one
/// place (e.g. the Showcase tab's filter chip) is consistent everywhere.
final socialFollowingOnlyProvider = StateProvider<bool>((ref) => false);

/// Updates tray — a single bounded page (CLAUDE.md: "Instagram-stories-style"
/// horizontal strip, not an infinitely scrolling feed), grouped by seller
/// client-side (see UpdatesTray).
final updatesTrayProvider = FutureProvider.autoDispose((ref) {
  return ref.watch(updatesRepositoryProvider).updates(following: ref.watch(socialFollowingOnlyProvider));
});

/// Offers row — same "one bounded page" reasoning as the Updates tray.
final offersRowProvider = FutureProvider.autoDispose((ref) {
  return ref.watch(offersRepositoryProvider).offers(following: ref.watch(socialFollowingOnlyProvider));
});

class ShowcaseFeedState {
  const ShowcaseFeedState({required this.items, required this.hasMore, required this.page});

  final List<Showcase> items;
  final bool hasMore;
  final int page;
}

/// Drives the Showcase tab's vertical `PageView` — same shape as
/// DiscoveryFeedController (items/hasMore/page, refresh()/loadMore()), so
/// the same "load one page ahead as the user nears the end" pattern applies.
class ShowcaseFeedController extends AsyncNotifier<ShowcaseFeedState> {
  @override
  Future<ShowcaseFeedState> build() async {
    final result = await ref
        .watch(showcasesRepositoryProvider)
        .showcases(following: ref.watch(socialFollowingOnlyProvider), page: 1);
    return ShowcaseFeedState(items: result.items, hasMore: result.hasMore, page: result.currentPage);
  }

  Future<void> refresh() async {
    ref.invalidateSelf();
    await future;
  }

  Future<void> loadMore() async {
    final current = state.value;
    if (current == null || !current.hasMore) return;

    final result = await ref
        .read(showcasesRepositoryProvider)
        .showcases(following: ref.read(socialFollowingOnlyProvider), page: current.page + 1);

    state = AsyncData(
      ShowcaseFeedState(
        items: [...current.items, ...result.items],
        hasMore: result.hasMore,
        page: result.currentPage,
      ),
    );
  }
}

final showcaseFeedProvider = AsyncNotifierProvider<ShowcaseFeedController, ShowcaseFeedState>(
  ShowcaseFeedController.new,
);

const _seenMarkKeyPrefix = 'updates_seen_';

/// Per-shop "last saw an Update" timestamps for the tray's unseen yellow
/// ring (CLAUDE.md Part 3) — purely local/device state (see DECISIONS.md:
/// not synced server-side), backed by `AppDatabase`'s generic key/value
/// cache table rather than a new drift table.
class UpdateSeenMarks extends AsyncNotifier<Map<int, DateTime>> {
  @override
  Future<Map<int, DateTime>> build() async {
    final rows = await ref.watch(appDatabaseProvider).getKeyValuesWithPrefix(_seenMarkKeyPrefix);
    return {
      for (final entry in rows.entries)
        int.parse(entry.key.substring(_seenMarkKeyPrefix.length)): DateTime.parse(entry.value),
    };
  }

  bool isSeen(int sellerId, DateTime latestUpdateAt) {
    final seenAt = state.value?[sellerId];
    return seenAt != null && !latestUpdateAt.isAfter(seenAt);
  }

  Future<void> markSeen(int sellerId) async {
    final now = DateTime.now();
    await ref.read(appDatabaseProvider).setKeyValue('$_seenMarkKeyPrefix$sellerId', now.toIso8601String());
    state = AsyncData({...?state.value, sellerId: now});
  }
}

final updateSeenMarksProvider = AsyncNotifierProvider<UpdateSeenMarks, Map<int, DateTime>>(UpdateSeenMarks.new);

/// One seller's own Showcases — the shop profile's Showcase tab, distinct
/// from the global vertical feed [showcaseFeedProvider] drives.
final sellerShowcasesProvider = FutureProvider.family.autoDispose<List<Showcase>, int>((ref, sellerId) async {
  final result = await ref.watch(showcasesRepositoryProvider).showcases(sellerId: sellerId);
  return result.items;
});

/// One seller's own active Updates — the shop profile's Updates tab.
final sellerUpdatesProvider = FutureProvider.family.autoDispose<List<SocialUpdate>, int>((ref, sellerId) async {
  final result = await ref.watch(updatesRepositoryProvider).updates(sellerId: sellerId);
  return result.items;
});
