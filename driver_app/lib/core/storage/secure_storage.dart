import 'package:flutter_secure_storage/flutter_secure_storage.dart';

/// The Sanctum token and the "recognised device" tokens from password
/// sign-in. One account at a time: a driver phone belongs to one driver.
class DriverSecureStorage {
  DriverSecureStorage([FlutterSecureStorage? storage])
      : _storage = storage ??
            const FlutterSecureStorage(
              aOptions: AndroidOptions(encryptedSharedPreferences: true),
            );

  final FlutterSecureStorage _storage;

  static const _tokenKey = 'driver.token';
  static const _userIdKey = 'driver.user_id';
  static const _deviceTokenPrefix = 'driver.device_token.';

  String? _cachedToken;
  bool _loaded = false;

  Future<String?> readToken() async {
    if (_loaded) return _cachedToken;
    _cachedToken = await _storage.read(key: _tokenKey);
    _loaded = true;
    return _cachedToken;
  }

  Future<void> writeSession({required String token, required int userId}) async {
    await _storage.write(key: _tokenKey, value: token);
    await _storage.write(key: _userIdKey, value: '$userId');
    _cachedToken = token;
    _loaded = true;
  }

  Future<int?> readUserId() async => int.tryParse(await _storage.read(key: _userIdKey) ?? '');

  Future<void> clearSession() async {
    await _storage.delete(key: _tokenKey);
    await _storage.delete(key: _userIdKey);
    _cachedToken = null;
    _loaded = true;
  }

  Future<String?> readDeviceToken(String login) => _storage.read(key: '$_deviceTokenPrefix${login.toLowerCase()}');

  Future<void> writeDeviceToken(String login, String token) =>
      _storage.write(key: '$_deviceTokenPrefix${login.toLowerCase()}', value: token);
}
