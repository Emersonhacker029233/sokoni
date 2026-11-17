import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_exception.dart';
import '../../../core/providers.dart';
import '../../../data/api/report_api.dart';
import '../../../data/models/blocked_account.dart';
import '../../../data/repositories/moderation_repository.dart';
import '../../chat/providers/chat_providers.dart';
import '../../discovery/providers/discovery_providers.dart';
import '../../discovery/providers/feed_providers.dart';
import '../../notifications/providers/notification_providers.dart';
import '../../product/providers/product_detail_providers.dart';
import '../../seller/providers/seller_providers.dart';
import '../../social/providers/social_providers.dart';

final moderationRepositoryProvider = Provider<ModerationRepository>((ref) {
  return ModerationRepository(dio: ref.watch(dioProvider), reportApi: ref.watch(reportApiProvider));
});

/// Who the signed-in user blocked, as the app's own filter (Apple
/// Guideline 1.2: blocking removes the user's content from the blocker's
/// feed "instantly"). The server already leaves their content out of every
/// list; this lets the cards already on screen, and anything served from
/// the offline cache, disappear the moment Block is tapped.
class BlockedAccounts {
  const BlockedAccounts(this.accounts);

  static const empty = BlockedAccounts([]);

  final List<BlockedAccount> accounts;

  /// Content written by this person (comments, reviews, chats).
  bool hidesUser(int? userId) => userId != null && accounts.any((a) => a.userId == userId);

  /// Content that belongs to this shop (listings, offers, showcases, updates).
  bool hidesShop(int? sellerId) => sellerId != null && accounts.any((a) => a.sellerId == sellerId);

  BlockedAccount? forUser(int? userId) => accounts.where((a) => a.userId == userId).firstOrNull;

  BlockedAccount? forShop(int? sellerId) => accounts.where((a) => a.sellerId == sellerId).firstOrNull;
}

class BlockedAccountsController extends AsyncNotifier<BlockedAccounts> {
  @override
  Future<BlockedAccounts> build() async {
    if (!ref.watch(authStateProvider).isAuthenticated) return BlockedAccounts.empty;
    try {
      return BlockedAccounts(await ref.read(moderationRepositoryProvider).blocks());
    } on ApiException {
      // Never in the way of browsing: the server filters every list on
      // its own, so a failed load here only loses the instant client-side
      // hiding until the next start.
      return BlockedAccounts.empty;
    }
  }

  /// Blocks a person ([userId]) or a shop's owner ([sellerId]), then
  /// hides their content at once and refetches every list.
  Future<BlockedAccount> block({int? userId, int? sellerId, String? reason}) async {
    final account = await ref.read(moderationRepositoryProvider).block(userId: userId, sellerId: sellerId, reason: reason);
    final current = state.value ?? BlockedAccounts.empty;
    state = AsyncData(BlockedAccounts([account, ...current.accounts.where((a) => a.userId != account.userId)]));
    _refetchContent();
    return account;
  }

  Future<void> unblock(int userId) async {
    await ref.read(moderationRepositoryProvider).unblock(userId);
    final current = state.value ?? BlockedAccounts.empty;
    state = AsyncData(BlockedAccounts(current.accounts.where((a) => a.userId != userId).toList()));
    _refetchContent();
  }

  /// Every server list that leaves blocked users out, so the next read of
  /// each one comes back from the server already filtered. Search results
  /// are not refetched (that would empty the person's search); the search
  /// screen filters what it already shows instead.
  void _refetchContent() {
    ref
      ..invalidate(forYouFeedProvider)
      ..invalidate(discoveryFeedProvider)
      ..invalidate(commentThreadProvider)
      ..invalidate(productDetailProvider)
      ..invalidate(sellerProfileProvider)
      ..invalidate(sellerReviewsProvider)
      ..invalidate(conversationsProvider)
      ..invalidate(chatThreadProvider)
      ..invalidate(notificationsProvider)
      ..invalidate(updatesTrayProvider)
      ..invalidate(offersRowProvider)
      ..invalidate(showcaseFeedProvider)
      ..invalidate(sellerShowcasesProvider);
  }
}

final blockedAccountsProvider = AsyncNotifierProvider<BlockedAccountsController, BlockedAccounts>(
  BlockedAccountsController.new,
);

/// The current block list, empty while it loads or for a guest.
final blockedFilterProvider = Provider<BlockedAccounts>((ref) {
  return ref.watch(blockedAccountsProvider).value ?? BlockedAccounts.empty;
});
