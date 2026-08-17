import 'dart:convert';
import 'dart:typed_data';

import 'package:connectivity_plus/connectivity_plus.dart';
import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:sokoni/core/network/api_exception.dart';
import 'package:sokoni/core/network/dio_client.dart';
import 'package:sokoni/core/providers.dart';
import 'package:sokoni/core/storage/secure_storage.dart';
import 'package:sokoni/features/discovery/providers/feed_providers.dart';

/// Never calls a platform channel — a real [SokoniSecureStorage] would
/// throw MissingPluginException in this plain test environment, which
/// would fail the request for an unrelated reason and defeat the point of
/// this test (this app has no signed-in-only requests among the ones
/// exercised here, so "no token" is the correct behaviour anyway).
class _NoTokenSecureStorage extends SokoniSecureStorage {
  @override
  Future<String?> readToken() async => null;
}

/// Returns one canned successful response for every request, regardless
/// of connectivity state — this is the thing under test: that a request
/// actually reaches this adapter (and succeeds) even while
/// [connectivityProvider] reports the device offline.
class _AlwaysSucceedsAdapter implements HttpClientAdapter {
  int callCount = 0;

  @override
  Future<ResponseBody> fetch(RequestOptions options, Stream<Uint8List>? requestStream, Future<void>? cancelFuture) async {
    callCount++;
    final body = jsonEncode({
      'data': <Map<String, dynamic>>[],
      'meta': {'current_page': 1, 'last_page': 1, 'total': 0},
    });
    return ResponseBody.fromString(body, 200, headers: {
      Headers.contentTypeHeader: [Headers.jsonContentType],
    });
  }

  @override
  void close({bool force = false}) {}
}

class _ConnectionErrorAdapter implements HttpClientAdapter {
  @override
  Future<ResponseBody> fetch(RequestOptions options, Stream<Uint8List>? requestStream, Future<void>? cancelFuture) {
    throw DioException.connectionError(requestOptions: options, reason: 'no route to host');
  }

  @override
  void close({bool force = false}) {}
}

/// Plain `test()`, not `testWidgets()` — nothing here pumps a widget tree,
/// and `testWidgets`' binding was found (empirically, while diagnosing why
/// this test hung indefinitely) to need extra unrelated pumping for a
/// freshly-overridden StreamProvider to settle; a plain `test()` doesn't
/// have that problem and matches what this test actually exercises: the
/// data layer, not any UI.
void main() {
  test(
    'the repository layer attempts a request even when connectivity reports offline '
    '(regression: connectivity must never gate a request, only inform an advisory banner)',
    () async {
      final adapter = _AlwaysSucceedsAdapter();

      final container = ProviderContainer(
        overrides: [
          // Reports offline for the entire test — if anything in the
          // request path checked this before attempting the call, the
          // adapter below would never be hit and this test would fail.
          connectivityProvider.overrideWith((ref) => Stream.value([ConnectivityResult.none])),
          secureStorageProvider.overrideWithValue(_NoTokenSecureStorage()),
          dioProvider.overrideWith((ref) {
            final dio = buildDioClient(storage: ref.watch(secureStorageProvider), onUnauthenticated: () async {});
            dio.httpClientAdapter = adapter;
            return dio;
          }),
        ],
      );
      addTearDown(container.dispose);

      // Confirm the test's own premise: connectivity genuinely reports
      // offline at this point, so a subsequent successful request proves
      // the two are decoupled rather than the offline state never having
      // taken effect in the first place.
      //
      // Deliberately not `await container.read(connectivityProvider.future)`
      // — on a StreamProvider that has never been watched/listened to,
      // reading `.future` alone does not pump far enough for the override
      // stream's single event to actually resolve it, and hangs
      // indefinitely with nothing else pumping the event loop. An
      // explicit listener plus a couple of zero-duration pumps reliably
      // reaches the same state instead.
      container.listen(connectivityProvider, (_, _) {});
      await Future<void>.delayed(Duration.zero);
      await Future<void>.delayed(Duration.zero);
      expect(container.read(isOnlineProvider), isFalse);

      final result = await container.read(feedRepositoryProvider).feed();

      expect(adapter.callCount, 1, reason: 'the repository must attempt the HTTP request regardless of connectivity state');
      expect(result.items, isEmpty);
    },
  );

  test('a real connection failure (not just an offline report) still maps to a distinct, diagnosable message', () async {
    final container = ProviderContainer(
      overrides: [
        connectivityProvider.overrideWith((ref) => Stream.value([ConnectivityResult.none])),
        secureStorageProvider.overrideWithValue(_NoTokenSecureStorage()),
        dioProvider.overrideWith((ref) {
          final dio = buildDioClient(storage: ref.watch(secureStorageProvider), onUnauthenticated: () async {});
          dio.httpClientAdapter = _ConnectionErrorAdapter();
          return dio;
        }),
      ],
    );
    addTearDown(container.dispose);

    await expectLater(
      container.read(feedRepositoryProvider).feed(),
      throwsA(isA<NetworkException>()),
    );
  });
}
