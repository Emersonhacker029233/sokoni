import 'package:dio/dio.dart';
import 'package:flutter/widgets.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:sokoni/core/network/api_exception.dart';
import 'package:sokoni/data/api/catalog_api.dart';
import 'package:sokoni/data/repositories/product_repository.dart';
import 'package:sokoni/features/discovery/providers/discovery_providers.dart';
import 'package:sokoni/features/product/providers/favorites_providers.dart';

/// [ProductRepository]'s constructor requires real (non-nullable) `api`/
/// `dio` instances, but [setFavorite] is fully overridden below and never
/// touches either — a plain unconfigured `Dio()`/`CatalogApi(Dio())` just
/// satisfies the constructor, nothing more.
class _ScriptedProductRepository extends ProductRepository {
  _ScriptedProductRepository({this.throwing}) : super(api: CatalogApi(Dio()), cache: null, dio: Dio());

  /// Thrown on every `setFavorite` call when set — e.g.
  /// `UnauthenticatedException()` to script a guest 401, or
  /// `ServerException()` for a real failure.
  final Exception? throwing;
  final List<({int productId, bool favorited})> calls = [];

  @override
  Future<void> setFavorite(int productId, bool favorited) async {
    calls.add((productId: productId, favorited: favorited));
    final e = throwing;
    if (e != null) throw e;
  }
}

/// [favorites_providers.dart]'s functions take a `WidgetRef` (they're meant
/// to be called straight from a widget's `onTap`/callback) — the only way
/// to get a real one in a test is from an actual widget, not a bare
/// `ProviderContainer`. This pumps the smallest possible tree and hands the
/// captured `ref` back to the test body.
Future<WidgetRef> _pumpAndCaptureRef(WidgetTester tester, ProductRepository repo) async {
  late WidgetRef capturedRef;
  await tester.pumpWidget(
    ProviderScope(
      overrides: [productRepositoryProvider.overrideWithValue(repo)],
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
  testWidgets('double-tapping an unsaved product saves it optimistically and calls the API once', (tester) async {
    final repo = _ScriptedProductRepository();
    final ref = await _pumpAndCaptureRef(tester, repo);

    var unauthenticatedCalled = false;
    var failedMessage = '';
    await saveProductViaDoubleTap(
      ref,
      productId: 7,
      knownFavorited: false,
      onUnauthenticated: () => unauthenticatedCalled = true,
      onFailed: (message) => failedMessage = message,
    );

    expect(isProductFavorited(ref, productId: 7, knownFavorited: false), isTrue);
    expect(repo.calls, [(productId: 7, favorited: true)]);
    expect(unauthenticatedCalled, isFalse);
    expect(failedMessage, isEmpty);
  });

  testWidgets(
    'double-tapping an ALREADY-saved product does not unsave it and makes no redundant API call — '
    'the exact Instagram behaviour the feature is named for',
    (tester) async {
      final repo = _ScriptedProductRepository();
      final ref = await _pumpAndCaptureRef(tester, repo);

      var unauthenticatedCalled = false;
      // Already known-favourited from the server.
      await saveProductViaDoubleTap(
        ref,
        productId: 9,
        knownFavorited: true,
        onUnauthenticated: () => unauthenticatedCalled = true,
        onFailed: (_) {},
      );

      expect(isProductFavorited(ref, productId: 9, knownFavorited: true), isTrue);
      expect(repo.calls, isEmpty, reason: 'already saved — no need to call the API again');
      expect(unauthenticatedCalled, isFalse);
    },
  );

  testWidgets(
    'a guest (401/UnauthenticatedException) gets onUnauthenticated, never onFailed — '
    'regression: this used to show the same "check your connection" message as a real network error',
    (tester) async {
      final repo = _ScriptedProductRepository(throwing: const UnauthenticatedException());
      final ref = await _pumpAndCaptureRef(tester, repo);

      var unauthenticatedCalled = false;
      var failedCalled = false;
      await saveProductViaDoubleTap(
        ref,
        productId: 11,
        knownFavorited: false,
        onUnauthenticated: () => unauthenticatedCalled = true,
        onFailed: (_) => failedCalled = true,
      );

      expect(unauthenticatedCalled, isTrue);
      expect(failedCalled, isFalse, reason: 'a guest must never see a failure toast');
      expect(isProductFavorited(ref, productId: 11, knownFavorited: false), isFalse, reason: 'reverted — the save never actually happened');
    },
  );

  testWidgets(
    'a real failure (not a 401) reports the actual exception message via onFailed, not a generic connection string',
    (tester) async {
      final repo = _ScriptedProductRepository(throwing: const ServerException('Something went wrong. Please try again.'));
      final ref = await _pumpAndCaptureRef(tester, repo);

      var unauthenticatedCalled = false;
      String? failedMessage;
      await saveProductViaDoubleTap(
        ref,
        productId: 3,
        knownFavorited: false,
        onUnauthenticated: () => unauthenticatedCalled = true,
        onFailed: (message) => failedMessage = message,
      );

      expect(unauthenticatedCalled, isFalse);
      expect(failedMessage, 'Something went wrong. Please try again.');
      expect(isProductFavorited(ref, productId: 3, knownFavorited: false), isFalse, reason: 'reverted after the API call failed');
    },
  );

  testWidgets('a second double-tap after a successful save is a no-op (idempotent across repeated double-taps)', (tester) async {
    final repo = _ScriptedProductRepository();
    final ref = await _pumpAndCaptureRef(tester, repo);

    await saveProductViaDoubleTap(ref, productId: 5, knownFavorited: false, onUnauthenticated: () {}, onFailed: (_) {});
    await saveProductViaDoubleTap(ref, productId: 5, knownFavorited: false, onUnauthenticated: () {}, onFailed: (_) {});

    expect(repo.calls.length, 1);
  });
}
