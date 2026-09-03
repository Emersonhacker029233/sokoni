import 'package:flutter_test/flutter_test.dart';

import 'package:sokoni/core/storage/secure_storage.dart';

/// In-memory stand-in for the real platform-channel-backed storage, sharing
/// [backing] across instances the way the real Android keystore/
/// EncryptedSharedPreferences would persist across two separate
/// [SokoniSecureStorage] instances in the same process.
///
/// This proves [SokoniSecureStorage]'s own read/write contract (key names,
/// the int<->String conversion for the user id, `clearSession` removing
/// both keys and nothing else) round-trips correctly. It does NOT — and
/// cannot, from a plain `flutter test` with no platform channel and no
/// real OS process — prove that a write actually reaches disk before an
/// Android process is killed. That specific question (see DECISIONS.md:
/// flutter_secure_storage's Android `write()` uses `apply()`, not
/// `commit()`, in every version checked) can only be answered by a real
/// device: sign in, force-stop or swipe the app away, relaunch, confirm
/// still signed in. No Android device or emulator is available in this
/// environment (`flutter devices` lists only Windows/Chrome/Edge, `adb` is
/// not on PATH) to automate that check here.
class _InMemorySecureStorage extends SokoniSecureStorage {
  _InMemorySecureStorage(this.backing);

  final Map<String, String> backing;

  @override
  Future<String?> readToken() async => backing['sokoni.auth_token'];

  @override
  Future<void> writeToken(String token) async => backing['sokoni.auth_token'] = token;

  @override
  Future<int?> readUserId() async {
    final raw = backing['sokoni.user_id'];
    return raw == null ? null : int.tryParse(raw);
  }

  @override
  Future<void> writeUserId(int id) async => backing['sokoni.user_id'] = '$id';

  @override
  Future<void> clearSession() async {
    backing.remove('sokoni.auth_token');
    backing.remove('sokoni.user_id');
  }
}

void main() {
  test('a token written by one instance is read back correctly by a second instance sharing the same store', () async {
    final backing = <String, String>{};
    final writer = _InMemorySecureStorage(backing);
    final reader = _InMemorySecureStorage(backing);

    await writer.writeToken('a-real-sanctum-token');
    await writer.writeUserId(42);

    expect(await reader.readToken(), 'a-real-sanctum-token');
    expect(await reader.readUserId(), 42);
  });

  test('clearSession removes exactly the token and user id, nothing else', () async {
    final backing = <String, String>{'sokoni.auth_token': 't', 'sokoni.user_id': '1', 'unrelated.key': 'keep-me'};
    final storage = _InMemorySecureStorage(backing);

    await storage.clearSession();

    expect(await storage.readToken(), isNull);
    expect(await storage.readUserId(), isNull);
    expect(backing['unrelated.key'], 'keep-me');
  });

  test('reading before anything was ever written returns null, not an error', () async {
    final storage = _InMemorySecureStorage(<String, String>{});

    expect(await storage.readToken(), isNull);
    expect(await storage.readUserId(), isNull);
  });
}
