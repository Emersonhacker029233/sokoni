import 'dart:async';

import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_exception.dart';
import '../../../core/providers.dart';
import '../../../data/api/review_api.dart';
import '../../../data/api/seller_api.dart';
import '../../../data/models/paginated_result.dart';
import '../../../data/models/review.dart';
import '../../../data/models/seller_dashboard_stats.dart';
import '../../../data/models/seller_profile.dart';
import '../../../data/repositories/review_repository.dart';
import '../../../data/repositories/seller_repository.dart';

final sellerApiProvider = Provider<SellerApi>((ref) => SellerApi(ref.watch(dioProvider)));

final reviewApiProvider = Provider<ReviewApi>((ref) => ReviewApi(ref.watch(dioProvider)));

final reviewRepositoryProvider = Provider<ReviewRepository>((ref) {
  return ReviewRepository(api: ref.watch(reviewApiProvider));
});

final sellerRepositoryProvider = Provider<SellerRepository>((ref) {
  return SellerRepository(api: ref.watch(sellerApiProvider), dio: ref.watch(dioProvider));
});

final sellerProfileProvider = FutureProvider.family<SellerProfile, String>((ref, handle) {
  return ref.watch(sellerRepositoryProvider).byHandle(handle);
});

final sellerReviewsProvider = FutureProvider.family<(PaginatedResult<Review>, Map<String, int>), String>(
  (ref, handle) => ref.watch(sellerRepositoryProvider).reviews(handle),
);

/// Owner-only dashboard strip (CLAUDE.md Part 4).
final sellerDashboardProvider = FutureProvider.family.autoDispose<SellerDashboardStats, int>(
  (ref, sellerId) => ref.watch(sellerRepositoryProvider).dashboard(sellerId),
);

/// Optimistic per-shop follow overrides — same "instant tap feedback,
/// [SellerProfile.isFollowing] is the fallback" shape as FavoriteOverrides.
/// Keyed by seller id (identity used everywhere else in the app); the
/// follow/unfollow API calls themselves take the shop's handle.
class FollowOverrides extends Notifier<Map<int, bool>> {
  @override
  Map<int, bool> build() => {};

  void set(int sellerId, bool value) {
    state = {...state, sellerId: value};
  }
}

final followOverridesProvider = NotifierProvider<FollowOverrides, Map<int, bool>>(FollowOverrides.new);

/// Both [SellerProfile] (full shop profile) and [SellerSummary] (the
/// compact embed on a product/feed card) expose `id`/`handle`/`isFollowing`
/// — [isSellerFollowed]/[toggleSellerFollow] work off those three fields
/// directly rather than requiring one concrete model, so the same
/// optimistic-follow mechanism covers both the shop profile's Follow
/// button and the "For You" feed card's.
bool isSellerFollowed(WidgetRef ref, {required int sellerId, required bool isFollowing}) {
  return ref.watch(followOverridesProvider)[sellerId] ?? isFollowing;
}

/// Haptic on every toggle (CLAUDE.md Part 5) — centralised here rather
/// than at each call site, same reasoning as `toggleProductFavorite`. Same
/// [onUnauthenticated]/[onFailed] split too, and for the same reason: a
/// guest tapping Follow used to fail completely silently (not even a
/// toast) rather than being invited to sign in — see DECISIONS.md.
Future<void> toggleSellerFollow(
  WidgetRef ref, {
  required int sellerId,
  required String handle,
  required bool isFollowing,
  required VoidCallback onUnauthenticated,
  required void Function(String message) onFailed,
}) async {
  unawaited(HapticFeedback.selectionClick());
  final current = ref.read(followOverridesProvider)[sellerId] ?? isFollowing;
  final next = !current;
  ref.read(followOverridesProvider.notifier).set(sellerId, next);
  try {
    if (next) {
      await ref.read(sellerRepositoryProvider).follow(handle);
    } else {
      await ref.read(sellerRepositoryProvider).unfollow(handle);
    }
  } catch (e) {
    ref.read(followOverridesProvider.notifier).set(sellerId, current);
    if (e is UnauthenticatedException) {
      onUnauthenticated();
    } else {
      onFailed(e is ApiException ? e.message : '$e');
    }
  }
}

/// Starts a chat about [productId] with [sellerId] — both `feed_card.dart`'s
/// chat icon and the product detail page's "Message Seller" button used to
/// call `startConversation` directly with no error handling at all, so a
/// guest tapping either got a raw, uncaught exception rather than any kind
/// of prompt (see DECISIONS.md). Returns the new conversation id to
/// navigate to on success, or `null` if the attempt didn't go through — a
/// guest was already shown [onUnauthenticated]'s prompt, or a real failure
/// was already reported via [onFailed].
Future<int?> startConversationOrPromptSignIn(
  WidgetRef ref, {
  required int sellerId,
  int? productId,
  required VoidCallback onUnauthenticated,
  required void Function(String message) onFailed,
}) async {
  try {
    return await ref.read(sellerRepositoryProvider).startConversation(sellerId: sellerId, productId: productId);
  } catch (e) {
    if (e is UnauthenticatedException) {
      onUnauthenticated();
    } else {
      onFailed(e is ApiException ? e.message : '$e');
    }
    return null;
  }
}
