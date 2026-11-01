import '../../core/network/dio_client.dart';
import '../../core/storage/secure_storage.dart';
import '../api/auth_api.dart';
import '../models/auth_session.dart';

/// Result of a password sign-in: signed in, or an SMS code is needed
/// first (new device, or two-step sign-in turned on).
class LoginResult {
  const LoginResult.signedIn(AuthSession this.session) : codeExpiresAt = null;
  const LoginResult.requiresCode(DateTime this.codeExpiresAt) : session = null;

  final AuthSession? session;
  final DateTime? codeExpiresAt;

  bool get requiresCode => codeExpiresAt != null;
}

class AuthRepository {
  AuthRepository({required AuthApi api, required DriverSecureStorage storage})
      : _api = api,
        _storage = storage;

  final AuthApi _api;
  final DriverSecureStorage _storage;

  /// Sends the 6-digit SMS code. [isNewAccount] means the name field is needed.
  Future<({bool isNewAccount, DateTime? expiresAt})> requestOtp(String phoneE164, String locale) async {
    try {
      final json = await _api.requestOtp({'phone': phoneE164, 'locale': locale}) as Map<String, dynamic>;
      return (
        isNewAccount: json['is_new_account'] == true,
        expiresAt: DateTime.tryParse('${json['expires_at']}'),
      );
    } catch (e) {
      throw mapDioError(e);
    }
  }

  Future<AuthSession> verifyOtp({required String phoneE164, required String code, String? name}) async {
    try {
      final json = await _api.verifyOtp({
        'phone': phoneE164,
        'code': code,
        if (name != null && name.trim().isNotEmpty) 'name': name.trim(),
      });
      return await _persist(AuthSession.fromJson(json as Map<String, dynamic>));
    } catch (e) {
      throw mapDioError(e);
    }
  }

  Future<LoginResult> login({required String login, required String password}) async {
    try {
      final deviceToken = await _storage.readDeviceToken(login);
      final json = await _api.login({
        'login': login,
        'password': password,
        'device_token': ?deviceToken,
      }) as Map<String, dynamic>;
      if (json['requires_code'] == true) {
        return LoginResult.requiresCode(DateTime.tryParse('${json['expires_at']}') ?? DateTime.now().add(const Duration(minutes: 10)));
      }
      return LoginResult.signedIn(await _persist(AuthSession.fromJson(json)));
    } catch (e) {
      throw mapDioError(e);
    }
  }

  Future<AuthSession> verifyLogin({required String login, required String code}) async {
    try {
      final json = await _api.verifyLogin({'login': login, 'code': code}) as Map<String, dynamic>;
      final session = await _persist(AuthSession.fromJson(json));
      if (json['device_token'] is String) await _storage.writeDeviceToken(login, json['device_token'] as String);
      return session;
    } catch (e) {
      throw mapDioError(e);
    }
  }

  Future<void> logout() async {
    try {
      await _api.logout();
    } catch (_) {
      // Signing out locally must work offline too; the token dies with the session row.
    }
    await _storage.clearSession();
  }

  Future<void> deleteAccount() async {
    try {
      await _api.deleteAccount();
    } catch (e) {
      throw mapDioError(e);
    }
    await _storage.clearSession();
  }

  Future<AuthSession> _persist(AuthSession session) async {
    await _storage.writeSession(token: session.token, userId: session.user.id);
    return session;
  }
}
