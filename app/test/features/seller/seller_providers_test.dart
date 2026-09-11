import 'package:dio/dio.dart';
import 'package:flutter/widgets.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:sokoni/core/network/api_exception.dart';
import 'package:sokoni/data/api/seller_api.dart';
import 'package:sokoni/data/repositories/seller_repository.dart';
import 'package:sokoni/features/seller/providers/seller_providers.dart';

/// [SellerRepository]'s constructor requires a real `SellerApi`/`Dio`, but
/// every method exercised below is fully overridden — a plain unconfigured
/// `Dio()`/`SellerApi(Dio())` just satisfies the constructor.
class _ScriptedSellerRepository extends SellerRepository {
  _ScriptedSellerRepository({this.throwing}) : super(api: SellerApi(Dio()), dio: Dio());

  final Exception? throwing;
  final List<String> followCalls = [];
  final List<String> unfollowCalls = [];
  final List<int> conversationCalls = [];

  @override
  Future<void> follow(String handle) async {
    followCalls.add(handle);
    final e = throwing;
    if (e != null) throw e;
  }

  @override
  Future<void> unfollow(String handle) async {
    unfollowCalls.add(handle);
    final e = throwing;
    if (e != null) throw e;
  }

  @override
  Future<int> startConversation({required int sellerId, int? productId}) async {
    conversationCalls.add(sellerId);
    final e = throwing;
    if (e != null) throw e;
    return 42;
  }
}

Future<WidgetRef> _pumpAndCaptureRef(WidgetTester tester, SellerRepository repo) async {
  late WidgetRef capturedRef;
  await tester.pumpWidget(
    ProviderScope(
      overrides: [sellerRepositoryProvider.overrideWithValue(repo)],
      child: Consumer(
        builder: (context, ref, _) {
          capturedRef = ref;
          return const SizedBox.shrink();
        },
      ),
    ),
  );
  return capturedRef;
}

void main() {
  group('toggleSellerFollow', () {
    testWidgets('follows successfully with no unauthenticated/failed callback firing', (tester) async {
      final repo = _ScriptedSellerRepository();
      final ref = await _pumpAndCaptureRef(tester, repo);

      var unauthenticatedCalled = false;
      var failedCalled = false;
      await toggleSellerFollow(
        ref,
        sellerId: 1,
        handle: 'amina',
        isFollowing: false,
        onUnauthenticated: () => unauthenticatedCalled = true,
        onFailed: (_) => failedCalled = true,
      );

      expect(isSellerFollowed(ref, sellerId: 1, isFollowing: false), isTrue);
      expect(repo.followCalls, ['amina']);
      expect(unauthenticatedCalled, isFalse);
      expect(failedCalled, isFalse);
    });

    testWidgets(
      'a guest (401) gets onUnauthenticated and the optimistic follow reverts — '
      'regression: this previously failed completely silently, no feedback at all',
      (tester) async {
        final repo = _ScriptedSellerRepository(throwing: const UnauthenticatedException());
        final ref = await _pumpAndCaptureRef(tester, repo);

        var unauthenticatedCalled = false;
        var failedCalled = false;
        await toggleSellerFollow(
          ref,
          sellerId: 2,
          handle: 'baraka',
          isFollowing: false,
          onUnauthenticated: () => unauthenticatedCalled = true,
          onFailed: (_) => failedCalled = true,
        );

        expect(unauthenticatedCalled, isTrue);
        expect(failedCalled, isFalse);
        expect(isSellerFollowed(ref, sellerId: 2, isFollowing: false), isFalse, reason: 'reverted — never actually followed');
      },
    );

    testWidgets('a real failure reports the actual message via onFailed', (tester) async {
      final repo = _ScriptedSellerRepository(throwing: const ServerException('Something went wrong. Please try again.'));
      final ref = await _pumpAndCaptureRef(tester, repo);

      String? failedMessage;
      await toggleSellerFollow(
        ref,
        sellerId: 3,
        handle: 'catherine',
        isFollowing: false,
        onUnauthenticated: () {},
        onFailed: (message) => failedMessage = message,
      );

      expect(failedMessage, 'Something went wrong. Please try again.');
    });
  });

  group('displayedCustomerCount', () {
    testWidgets(
      'follows optimistically bump the shown count by one, before any server round trip resolves — '
      'regression: the count previously never moved at all on a successful follow',
      (tester) async {
        final repo = _ScriptedSellerRepository();
        final ref = await _pumpAndCaptureRef(tester, repo);

        expect(displayedCustomerCount(ref, sellerId: 1, baseCount: 10, baseIsFollowing: false), 10);

        await toggleSellerFollow(
          ref,
          sellerId: 1,
          handle: 'amina',
          isFollowing: false,
          onUnauthenticated: () {},
          onFailed: (_) {},
        );

        // Same base (10, isFollowing still false — the profile hasn't been
        // refetched in this test), but the optimistic override now says
        // "following", so the shown count reflects the follow immediately.
        expect(displayedCustomerCount(ref, sellerId: 1, baseCount: 10, baseIsFollowing: false), 11);
      },
    );

    testWidgets('unfollowing optimistically decrements the shown count by one', (tester) async {
      final repo = _ScriptedSellerRepository();
      final ref = await _pumpAndCaptureRef(tester, repo);

      await toggleSellerFollow(
        ref,
        sellerId: 1,
        handle: 'amina',
        isFollowing: true, // already following
        onUnauthenticated: () {},
        onFailed: (_) {},
      );

      expect(displayedCustomerCount(ref, sellerId: 1, baseCount: 10, baseIsFollowing: true), 9);
    });

    testWidgets(
      'a failed follow reverts the optimistic count along with the optimistic follow state — '
      'the count must not stay bumped when the request never actually went through',
      (tester) async {
        final repo = _ScriptedSellerRepository(throwing: const ServerException('Something went wrong. Please try again.'));
        final ref = await _pumpAndCaptureRef(tester, repo);

        await toggleSellerFollow(
          ref,
          sellerId: 1,
          handle: 'amina',
          isFollowing: false,
          onUnauthenticated: () {},
          onFailed: (_) {},
        );

        expect(displayedCustomerCount(ref, sellerId: 1, baseCount: 10, baseIsFollowing: false), 10);
      },
    );

    testWidgets(
      'once the base profile itself reflects the new follow state, the adjustment collapses back to zero — '
      'proving no double-count once toggleSellerFollow\'s post-success invalidation delivers a fresh profile',
      (tester) async {
        final repo = _ScriptedSellerRepository();
        final ref = await _pumpAndCaptureRef(tester, repo);
        ref.read(followOverridesProvider.notifier).set(1, true);

        // Base still says not-following (stale) — override adds one.
        expect(displayedCustomerCount(ref, sellerId: 1, baseCount: 10, baseIsFollowing: false), 11);
        // Base has refetched and now agrees with the override — no more
        // adjustment applied, avoiding a double-count.
        expect(displayedCustomerCount(ref, sellerId: 1, baseCount: 11, baseIsFollowing: true), 11);
      },
    );
  });

  group('startConversationOrPromptSignIn', () {
    testWidgets('returns the new conversation id on success', (tester) async {
      final repo = _ScriptedSellerRepository();
      final ref = await _pumpAndCaptureRef(tester, repo);

      final result = await startConversationOrPromptSignIn(
        ref,
        sellerId: 1,
        productId: 5,
        onUnauthenticated: () {},
        onFailed: (_) {},
      );

      expect(result, 42);
      expect(repo.conversationCalls, [1]);
    });

    testWidgets(
      'a guest gets onUnauthenticated and a null result, never an uncaught exception — '
      'regression: both call sites (feed card, product detail) previously had no error handling at all',
      (tester) async {
        final repo = _ScriptedSellerRepository(throwing: const UnauthenticatedException());
        final ref = await _pumpAndCaptureRef(tester, repo);

        var unauthenticatedCalled = false;
        final result = await startConversationOrPromptSignIn(
          ref,
          sellerId: 1,
          onUnauthenticated: () => unauthenticatedCalled = true,
          onFailed: (_) {},
        );

        expect(unauthenticatedCalled, isTrue);
        expect(result, isNull);
      },
    );
  });
}
