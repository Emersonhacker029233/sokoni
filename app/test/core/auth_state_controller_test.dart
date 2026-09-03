import 'dart:async';

import 'package:fake_async/fake_async.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:sokoni/core/providers.dart';
import 'package:sokoni/core/storage/secure_storage.dart';

/// Test double for the on-device keystore read `AuthStateController` depends
/// on — never touches a platform channel (a real [SokoniSecureStorage]
/// would throw `MissingPluginException` in this plain test environment),
/// matching the pattern already established in
/// `connectivity_does_not_gate_requests_test.dart`.
class _ScriptedSecureStorage extends SokoniSecureStorage {
  _ScriptedSecureStorage({this.token, this.delay, this.throwOnRead = false, this.neverCompletes = false});

  final String? token;
  final Duration? delay;
  final bool throwOnRead;
  final bool neverCompletes;

  @override
  Future<String?> readToken() {
    if (neverCompletes) return Completer<String?>().future;
    return Future<String?>.delayed(delay ?? Duration.zero, () {
      if (throwOnRead) throw Exception('keystore decrypt failed');
      return token;
    });
  }
}

void main() {
  group('cold start restores the real state from storage', () {
    test('a stored token restores AuthStatus.authenticated — the actual bug report: '
        'signing in works, but the app forgot on restart', () async {
      final container = ProviderContainer(
        overrides: [
          secureStorageProvider.overrideWithValue(_ScriptedSecureStorage(token: 'a-real-token')),
        ],
      );
      addTearDown(container.dispose);

      expect(container.read(authStateProvider).isLoading, isTrue);

      await container.read(authStateProvider.notifier).retry();

      expect(container.read(authStateProvider).isAuthenticated, isTrue);
    });

    test('no stored token correctly restores AuthStatus.unauthenticated (a genuine guest, not a bug)', () async {
      final container = ProviderContainer(
        overrides: [
          secureStorageProvider.overrideWithValue(_ScriptedSecureStorage(token: null)),
        ],
      );
      addTearDown(container.dispose);

      await container.read(authStateProvider.notifier).retry();

      final state = container.read(authStateProvider);
      expect(state.isAuthenticated, isFalse);
      expect(state.isUnknown, isFalse);
    });

    test('a slow-but-eventually-successful read still resolves to authenticated — '
        'the read must complete before auth state is decided, not be cut off early', () async {
      final container = ProviderContainer(
        overrides: [
          secureStorageProvider.overrideWithValue(
            _ScriptedSecureStorage(token: 'a-real-token', delay: const Duration(seconds: 8)),
          ),
        ],
      );
      addTearDown(container.dispose);

      fakeAsync((async) {
        unawaited(container.read(authStateProvider.notifier).retry());
        async.elapse(const Duration(seconds: 8));

        expect(container.read(authStateProvider).isAuthenticated, isTrue);
      });
    });
  });

  group('a failed read must never be reported as signed out', () {
    test('a thrown exception during the read resolves to AuthStatus.unknown, not unauthenticated', () async {
      final container = ProviderContainer(
        overrides: [
          secureStorageProvider.overrideWithValue(_ScriptedSecureStorage(throwOnRead: true)),
        ],
      );
      addTearDown(container.dispose);

      await container.read(authStateProvider.notifier).retry();

      final state = container.read(authStateProvider);
      expect(state.isUnknown, isTrue, reason: 'a read failure is not proof the user signed out');
      expect(state.isAuthenticated, isFalse);
    });

    test('a read that never completes hits the safety-net timeout and resolves to unknown, never unauthenticated', () {
      final container = ProviderContainer(
        overrides: [
          secureStorageProvider.overrideWithValue(_ScriptedSecureStorage(neverCompletes: true)),
        ],
      );
      addTearDown(container.dispose);

      fakeAsync((async) {
        unawaited(container.read(authStateProvider.notifier).retry());

        // Still undetermined well before the safety net — a real session
        // must not be declared lost just because the read is taking a while.
        async.elapse(const Duration(seconds: 5));
        expect(container.read(authStateProvider).isLoading, isTrue);

        async.elapse(const Duration(seconds: 10));
        final state = container.read(authStateProvider);
        expect(state.isUnknown, isTrue);
        expect(state.isAuthenticated, isFalse);
      });
    });

    test('retry() recovers from unknown once the underlying read succeeds', () async {
      // A single fake instance whose behaviour changes between calls —
      // simulating "the transient problem went away" between the initial
      // failed load and the user tapping retry.
      var shouldFail = true;
      final storage = _RecoveringSecureStorage(failFirst: () => shouldFail);
      final container = ProviderContainer(
        overrides: [secureStorageProvider.overrideWithValue(storage)],
      );
      addTearDown(container.dispose);

      await container.read(authStateProvider.notifier).retry();
      expect(container.read(authStateProvider).isUnknown, isTrue);

      shouldFail = false;
      await container.read(authStateProvider.notifier).retry();
      expect(container.read(authStateProvider).isAuthenticated, isTrue);
    });
  });
}

class _RecoveringSecureStorage extends SokoniSecureStorage {
  _RecoveringSecureStorage({required this.failFirst});

  final bool Function() failFirst;

  @override
  Future<String?> readToken() async {
    if (failFirst()) throw Exception('transient failure');
    return 'a-real-token';
  }
}
