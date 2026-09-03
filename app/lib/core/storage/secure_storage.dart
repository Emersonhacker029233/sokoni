import 'package:flutter_secure_storage/flutter_secure_storage.dart';

/// Thin wrapper around [FlutterSecureStorage] scoped to the handful of
/// secrets Sokoni persists on-device: the Sanctum bearer token and the
/// signed-in user's id (used to key the drift cache per-account).
///
/// `encryptedSharedPreferences: true` routes storage through AndroidX
/// Security's `EncryptedSharedPreferences`/`MasterKey` rather than this
/// plugin's own legacy RSA_ECB_PKCS1Padding-wrapped-AES cipher (the
/// previous default) — Google's actively maintained, better-audited
/// implementation. The plugin migrates any existing legacy-cipher values
/// across transparently on first read after this change, so no session is
/// lost by turning it on.
///
/// This does NOT fix the separate, real durability gap confirmed in the
/// plugin's own source (both 9.2.4, pinned here, and 11.0.0): every write
/// path calls `SharedPreferences.Editor.apply()`, not `.commit()`, so a
/// value can be considered "written" in Dart before it has actually
/// reached disk. Android's OS flushes pending `apply()` writes during a
/// graceful `onStop()`, which covers most ways of closing an app, but not
/// an abrupt process kill. Fixing that fully would require patching or
/// replacing the plugin's native Android layer — out of scope here; see
/// DECISIONS.md.
class SokoniSecureStorage {
  SokoniSecureStorage({FlutterSecureStorage? storage})
    : _storage = storage ?? const FlutterSecureStorage(aOptions: AndroidOptions(encryptedSharedPreferences: true));

  final FlutterSecureStorage _storage;

  static const _tokenKey = 'sokoni.auth_token';
  static const _userIdKey = 'sokoni.user_id';

  Future<String?> readToken() => _storage.read(key: _tokenKey);

  Future<void> writeToken(String token) => _storage.write(key: _tokenKey, value: token);

  Future<int?> readUserId() async {
    final raw = await _storage.read(key: _userIdKey);
    return raw == null ? null : int.tryParse(raw);
  }

  Future<void> writeUserId(int id) => _storage.write(key: _userIdKey, value: '$id');

  /// Clears the session on logout/401 — does not touch any other secrets.
  Future<void> clearSession() async {
    await _storage.delete(key: _tokenKey);
    await _storage.delete(key: _userIdKey);
  }
}
