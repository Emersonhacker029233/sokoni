import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:sokoni/core/storage/secure_storage.dart';

/// In-memory stand-in for the platform-channel-backed store, sharing
/// [backing] across instances the way the real Android keystore/
/// EncryptedSharedPreferences would persist across two separate
/// [SokoniSecureStorage] instances in the same process.
///
/// This proves [SokoniSecureStorage]'s real read/write contract — the
/// multi-account JSON blob, the active-account pointer, `clearSession`
/// advancing to the next remaining account — round-trips correctly
/// through the exact same code path production uses (only the platform
/// channel underneath is faked). It does NOT — and cannot, from a plain
/// `flutter test` with no platform channel and no real OS process —
/// prove that a write actually reaches disk before an Android process is
/// killed. That specific question (see DECISIONS.md:
/// flutter_secure_storage's Android `write()` uses `apply()`, not
/// `commit()`, in every version checked) can only be answered by a real
/// device: sign in, force-stop or swipe the app away, relaunch, confirm
/// still signed in. No Android device or emulator is available in this
/// environment (`flutter devices` lists only Windows/Chrome/Edge, `adb` is
/// not on PATH) to automate that check here.
class _InMemoryPlatformStorage extends FlutterSecureStorage {
  _InMemoryPlatformStorage(this.backing);

  final Map<String, String> backing;

  @override
  Future<String?> read({
    required String key,
    IOSOptions? iOptions,
    AndroidOptions? aOptions,
    LinuxOptions? lOptions,
    WebOptions? webOptions,
    MacOsOptions? mOptions,
    WindowsOptions? wOptions,
  }) async => backing[key];

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
  }) async {
    if (value == null) {
      backing.remove(key);
    } else {
      backing[key] = value;
    }
  }

  @override
  Future<void> delete({
    required String key,
    IOSOptions? iOptions,
    AndroidOptions? aOptions,
    LinuxOptions? lOptions,
    WebOptions? webOptions,
    MacOsOptions? mOptions,
    WindowsOptions? wOptions,
  }) async => backing.remove(key);
}

SokoniSecureStorage _storage(Map<String, String> backing) =>
    SokoniSecureStorage(storage: _InMemoryPlatformStorage(backing));

void main() {
  test('an account written by one instance is read back correctly by a second instance sharing the same store', () async {
    final backing = <String, String>{};
    final writer = _storage(backing);
    final reader = _storage(backing);

    await writer.addOrUpdateAccount(
      const StoredAccount(userId: 42, token: 'a-real-sanctum-token', name: 'Asha'),
    );

    expect(await reader.readToken(), 'a-real-sanctum-token');
    expect(await reader.readUserId(), 42);
  });

  test('clearSession with only one remembered account removes it and leaves no active account', () async {
    final backing = <String, String>{'unrelated.key': 'keep-me'};
    final storage = _storage(backing);
    await storage.addOrUpdateAccount(const StoredAccount(userId: 1, token: 't', name: 'Zawadi'));

    await storage.clearSession();

    expect(await storage.readToken(), isNull);
    expect(await storage.readUserId(), isNull);
    expect(backing['unrelated.key'], 'keep-me');
  });

  test('clearSession with two remembered accounts drops only the active one and switches to the other', () async {
    final backing = <String, String>{};
    final storage = _storage(backing);
    await storage.addOrUpdateAccount(const StoredAccount(userId: 1, token: 't1', name: 'Zawadi'));
    await storage.addOrUpdateAccount(const StoredAccount(userId: 2, token: 't2', name: 'Baraka'));
    expect(await storage.readUserId(), 2, reason: 'the most recently added account is active');

    await storage.clearSession();

    expect(await storage.readUserId(), 1);
    expect(await storage.readToken(), 't1');
    final remaining = await storage.readAccounts();
    expect(remaining.map((a) => a.userId), [1]);
  });

  test('switchActiveAccount moves the pointer without touching either account\'s stored token', () async {
    final backing = <String, String>{};
    final storage = _storage(backing);
    await storage.addOrUpdateAccount(const StoredAccount(userId: 1, token: 't1', name: 'Zawadi'));
    await storage.addOrUpdateAccount(const StoredAccount(userId: 2, token: 't2', name: 'Baraka'));

    await storage.switchActiveAccount(1);

    expect(await storage.readUserId(), 1);
    expect(await storage.readToken(), 't1');
    final accounts = await storage.readAccounts();
    expect(accounts.map((a) => a.userId), containsAll([1, 2]));
  });

  test('reading before anything was ever written returns null/empty, not an error', () async {
    final storage = _storage(<String, String>{});

    expect(await storage.readToken(), isNull);
    expect(await storage.readUserId(), isNull);
    expect(await storage.readAccounts(), isEmpty);
  });
}
