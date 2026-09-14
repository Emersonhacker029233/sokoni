import 'package:dio/dio.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:sokoni/core/motion/splash_transition.dart';
import 'package:sokoni/core/network/dio_client.dart';
import 'package:sokoni/core/providers.dart';
import 'package:sokoni/core/storage/secure_storage.dart';
import 'package:sokoni/main.dart';

/// Fails every request synchronously with a connection error, so
/// [ProductRepository] takes its offline-cache fallback path instead of
/// leaving a real, unmocked HTTP request pending when the test tears down.
class _FailFastAdapter implements HttpClientAdapter {
  @override
  Future<ResponseBody> fetch(
    RequestOptions options,
    Stream<Uint8List>? requestStream,
    Future<void>? cancelFuture,
  ) {
    throw DioException.connectionError(requestOptions: options, reason: 'offline in tests');
  }

  @override
  void close({bool force = false}) {}
}

/// Reproduces the real production crash (D-round 3, client feedback): a
/// value written under a previous flutter_secure_storage cipher
/// configuration surfaced as an uncaught `PlatformException` —
/// `BadPaddingException: BAD_DECRYPT` — the first time anything tried to
/// decrypt it, and that reached [AuthInterceptor]'s `onRequest` (called on
/// literally the first API request the app makes) with nothing catching
/// it, killing the app before it painted a frame. Every operation here
/// throws the same way the real corrupted keystore did, to prove
/// [SokoniSecureStorage] now absorbs it at every one of read/write/delete.
class _CorruptedPlatformStorage extends FlutterSecureStorage {
  const _CorruptedPlatformStorage();

  static final _decryptFailure = PlatformException(
    code: 'Exception encountered',
    message:
        'javax.crypto.BadPaddingException: error:1e000065:Cipher '
        'functions:OPENSSL_internal:BAD_DECRYPT',
  );

  @override
  Future<String?> read({
    required String key,
    IOSOptions? iOptions,
    AndroidOptions? aOptions,
    LinuxOptions? lOptions,
    WebOptions? webOptions,
    MacOsOptions? mOptions,
    WindowsOptions? wOptions,
  }) => throw _decryptFailure;

  @override
  Future<void> write({
    required String key,
    required String? value,
    IOSOptions? iOptions,
    AndroidOptions? aOptions,
    LinuxOptions? lOptions,
    WebOptions? webOptions,
    MacOsOptions? mOptions,
    WindowsOptions? wOptions,
  }) => throw _decryptFailure;

  @override
  Future<void> delete({
    required String key,
    IOSOptions? iOptions,
    AndroidOptions? aOptions,
    LinuxOptions? lOptions,
    WebOptions? webOptions,
    MacOsOptions? mOptions,
    WindowsOptions? wOptions,
  }) => throw _decryptFailure;

  @override
  Future<void> deleteAll({
    IOSOptions? iOptions,
    AndroidOptions? aOptions,
    LinuxOptions? lOptions,
    WebOptions? webOptions,
    MacOsOptions? mOptions,
    WindowsOptions? wOptions,
  }) => throw _decryptFailure;
}

void main() {
  testWidgets('splash settles into the home tab shell', (WidgetTester tester) async {
    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          dioProvider.overrideWith((ref) {
            final dio = buildDioClient(
              storage: ref.watch(secureStorageProvider),
              onUnauthenticated: () async {},
            );
            dio.httpClientAdapter = _FailFastAdapter();
            return dio;
          }),
        ],
        child: const SokoniApp(),
      ),
    );

    // Splash plays first (D-round 2: ~3s brand moment); pump it to
    // completion explicitly rather than pumpAndSettle — the home tab's
    // shimmer skeleton animates continuously while its network request is
    // pending, so pumpAndSettle would never find a quiet frame even with
    // the fail-fast adapter above. Reads the same constant SplashScreen's
    // own navigation timer targets, so this can't silently drift out of
    // sync with a future splash-duration change the way a hardcoded
    // number would.
    await tester.pump(SokoniSplashTransition.duration);
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 100));

    expect(find.text('Sokoni'), findsWidgets);

    // Several startup calls now have their own bounded timeouts (secure
    // storage read — AuthStateController._safetyNetTimeout, 10s; location
    // fetch, 8s — see DECISIONS.md) which, with no platform-channel mock
    // registered in this test, never resolve on their own and fall back to
    // their internal `Future.timeout` Timer instead. Flush past the longest
    // of those so none are still pending when the widget tree is torn down
    // at the end of the test — otherwise flutter_test's `!timersPending`
    // teardown check fails.
    await tester.pump(const Duration(seconds: 11));
  });

  testWidgets(
    'a corrupted/undecryptable secure store never crashes the app — it still reaches the feed, signed out',
    (WidgetTester tester) async {
      final corruptedStorage = SokoniSecureStorage(storage: const _CorruptedPlatformStorage());

      await tester.pumpWidget(
        ProviderScope(
          overrides: [
            secureStorageProvider.overrideWithValue(corruptedStorage),
            dioProvider.overrideWith((ref) {
              final dio = buildDioClient(
                storage: ref.watch(secureStorageProvider),
                onUnauthenticated: () async {},
              );
              dio.httpClientAdapter = _FailFastAdapter();
              return dio;
            }),
          ],
          child: const SokoniApp(),
        ),
      );

      await tester.pump(SokoniSplashTransition.duration);
      await tester.pump();
      await tester.pump(const Duration(milliseconds: 100));

      // The actual regression this guards: every secure-storage call this
      // app makes at startup (AuthStateController._loadInitial reading the
      // token, AuthInterceptor.onRequest reading it again on the first API
      // call) throws PlatformException here, exactly like the real
      // corrupted keystore did. Before the fix, that killed the app before
      // this point was ever reached.
      expect(find.text('Sokoni'), findsWidgets);
      expect(tester.takeException(), isNull);

      await tester.pump(const Duration(seconds: 11));
      expect(tester.takeException(), isNull);
    },
  );
}
