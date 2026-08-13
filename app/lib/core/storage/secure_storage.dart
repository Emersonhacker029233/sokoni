import 'package:flutter_secure_storage/flutter_secure_storage.dart';

/// Thin wrapper around [FlutterSecureStorage] scoped to the handful of
/// secrets Sokoni persists on-device: the Sanctum bearer token and the
/// signed-in user's id (used to key the drift cache per-account).
class SokoniSecureStorage {
  // AndroidOptions() defaults to AES-GCM storage encryption with RSA OAEP
  // key wrapping in flutter_secure_storage 11.x — no extra flags needed.
  SokoniSecureStorage({FlutterSecureStorage? storage})
    : _storage = storage ?? const FlutterSecureStorage(aOptions: AndroidOptions());

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
