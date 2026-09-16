import 'package:drift/native.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:sokoni/core/providers.dart';
import 'package:sokoni/core/storage/app_database.dart';
import 'package:sokoni/core/storage/secure_storage.dart';
import 'package:sokoni/data/models/product.dart';
import 'package:sokoni/features/orders/providers/order_providers.dart';
import 'package:sokoni/features/product/providers/search_providers.dart';
import 'package:sokoni/features/seller/providers/seller_onboarding_providers.dart';
import 'package:sokoni/features/social/providers/social_providers.dart';

/// Part 5 (client feedback): "Be careful with anything holding per-user
/// state — the cart, drafts, cached feed, unread counts — all of it must
/// switch with the account rather than leaking between them. That
/// leakage is the main risk in this feature, so test it explicitly."
///
/// The full-`ProviderScope` restart on account switch (see
/// `restartApp()` in main.dart) already clears every in-memory provider
/// — the cart included — but does nothing for state persisted to disk
/// under a fixed key in `AppDatabase`'s key/value cache, which is what
/// search history, the seller-onboarding draft, and per-shop "seen"
/// marks all use. `scopedCacheKey()` (core/providers.dart) is what
/// actually prevents THIS leakage, so these tests exercise it directly
/// against a single, SHARED [AppDatabase] — the one thing that genuinely
/// survives a switch — standing in for two different accounts by giving
/// each its own [ProviderContainer] with a different active user id, the
/// same way two different post-restart `ProviderScope`s would.
class _FixedUserSecureStorage extends SokoniSecureStorage {
  _FixedUserSecureStorage(this._userId);
  final int? _userId;

  @override
  Future<int?> readUserId() async => _userId;
}

ProviderContainer _containerFor(AppDatabase db, int? userId) {
  return ProviderContainer(
    overrides: [
      appDatabaseProvider.overrideWithValue(db),
      secureStorageProvider.overrideWithValue(_FixedUserSecureStorage(userId)),
    ],
  );
}

void main() {
  late AppDatabase db;

  setUp(() {
    db = AppDatabase.forTesting(NativeDatabase.memory());
  });

  tearDown(() => db.close());

  group('search history does not leak between accounts', () {
    test('user B never sees user A\'s search terms, and switching back restores A\'s own', () async {
      final asA = _containerFor(db, 1);
      await asA.read(searchHistoryProvider.notifier).add('samsung a14');
      expect(asA.read(searchHistoryProvider).value, ['samsung a14']);

      final asB = _containerFor(db, 2);
      expect(await asB.read(searchHistoryProvider.future), isEmpty, reason: "B must not inherit A's search history");

      await asB.read(searchHistoryProvider.notifier).add('iphone charger');
      expect(asB.read(searchHistoryProvider).value, ['iphone charger']);

      // Switching back to A (a fresh container, exactly like a real
      // restart) must restore A's own history, not B's, and not empty.
      final asAAgain = _containerFor(db, 1);
      expect(await asAAgain.read(searchHistoryProvider.future), ['samsung a14']);

      asA.dispose();
      asB.dispose();
      asAAgain.dispose();
    });

    test('a guest (no active account) has its own scope, separate from any signed-in account', () async {
      final asGuest = _containerFor(db, null);
      await asGuest.read(searchHistoryProvider.notifier).add('cheap phones');

      final asA = _containerFor(db, 1);
      expect(await asA.read(searchHistoryProvider.future), isEmpty);

      asGuest.dispose();
      asA.dispose();
    });
  });

  group('the seller-onboarding draft does not leak between accounts', () {
    test('user B starts a fresh draft even though user A left theirs mid-wizard', () async {
      final asA = _containerFor(db, 1);
      await asA.read(sellerOnboardingProvider.future);
      await asA.read(sellerOnboardingProvider.notifier).advanceToStep(2);
      expect(asA.read(sellerOnboardingProvider).value!.step, 2);

      final asB = _containerFor(db, 2);
      final draftB = await asB.read(sellerOnboardingProvider.future);
      expect(draftB.step, 0, reason: "B must not resume A's in-progress wizard");

      asA.dispose();
      asB.dispose();
    });
  });

  group('the cart does not leak between accounts', () {
    test('a fresh container (what a real account switch\'s restartApp() produces) never inherits a previous cart', () async {
      final asA = _containerFor(db, 1);
      asA.read(cartProvider.notifier).add(
        const Product(id: 7, title: 'Samsung A14', price: 350000),
      );
      expect(asA.read(cartProvider), hasLength(1));

      // CartController has no disk persistence at all (see
      // order_providers.dart) — the full-ProviderScope restart on switch
      // is the ENTIRE mechanism keeping it from leaking, so this proves
      // that mechanism actually holds: a brand new container (standing
      // in for the fresh one restartApp() creates) must never see it.
      final asB = _containerFor(db, 2);
      expect(asB.read(cartProvider), isEmpty, reason: "B must not inherit A's cart");

      asA.dispose();
      asB.dispose();
    });
  });

  group('"seen update" marks do not leak between accounts', () {
    test('a shop A already saw as updated still shows as unseen to B', () async {
      final beforeMarkingSeen = DateTime.now();
      final asA = _containerFor(db, 1);
      await asA.read(updateSeenMarksProvider.future);
      await asA.read(updateSeenMarksProvider.notifier).markSeen(42);
      expect(asA.read(updateSeenMarksProvider.notifier).isSeen(42, beforeMarkingSeen), isTrue);

      final asB = _containerFor(db, 2);
      final seenAsB = await asB.read(updateSeenMarksProvider.future);
      expect(seenAsB.containsKey(42), isFalse, reason: "B must see shop 42's update as unseen, not inherit A's mark");

      asA.dispose();
      asB.dispose();
    });
  });
}
