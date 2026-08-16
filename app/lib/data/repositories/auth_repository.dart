import '../../core/network/dio_client.dart';
import '../../core/storage/secure_storage.dart';
import '../api/auth_api.dart';
import '../models/auth_response.dart';
import '../models/user.dart';

/// Wraps [AuthApi] with token persistence — the only repository that
/// touches [SokoniSecureStorage] directly, since every other repository
/// gets its auth handled transparently by the Dio auth interceptor.
class AuthRepository {
  AuthRepository({required AuthApi api, required SokoniSecureStorage storage})
    : _api = api,
      _storage = storage;

  final AuthApi _api;
  final SokoniSecureStorage _storage;

  /// True if this phone number has never signed in before — lets the UI
  /// say plainly, right when the code is sent, that a new account is
  /// about to be created (CLAUDE.md Part 2 item 1).
  Future<bool> requestOtp(String phoneE164) async {
    try {
      final json = await _api.requestOtp({'phone': phoneE164});
      return json['is_new_account'] as bool? ?? false;
    } catch (e) {
      throw mapDioError(e);
    }
  }

  Future<AuthResponse> verifyOtp({required String phoneE164, required String code, String? name}) async {
    try {
      final response = await _api.verifyOtp({
        'phone': phoneE164,
        'code': code,
        'name': name,
      });
      await _storage.writeToken(response.token);
      await _storage.writeUserId(response.user.id);
      return response;
    } catch (e) {
      throw mapDioError(e);
    }
  }

  Future<AuthResponse> socialLogin({required String provider, required String token}) async {
    try {
      final response = await _api.socialLogin({'provider': provider, 'token': token});
      await _storage.writeToken(response.token);
      await _storage.writeUserId(response.user.id);
      return response;
    } catch (e) {
      throw mapDioError(e);
    }
  }

  /// One-time "buy / sell / decide later" answer for the intent screen
  /// shown right after a brand-new account's first sign-in — persisted
  /// server-side so it never reappears (CLAUDE.md Part 2 item 2).
  Future<SokoniUser> submitIntent(String intent) async {
    try {
      final json = await _api.updateIntent({'intent': intent});
      return SokoniUser.fromJson(json['data'] as Map<String, dynamic>);
    } catch (e) {
      throw mapDioError(e);
    }
  }

  Future<SokoniUser> me() async {
    try {
      final json = await _api.me();
      return SokoniUser.fromJson(json['data'] as Map<String, dynamic>);
    } catch (e) {
      throw mapDioError(e);
    }
  }

  Future<SokoniUser> acceptTerms(String version) async {
    try {
      final json = await _api.acceptTerms({'version': version});
      return SokoniUser.fromJson(json['data'] as Map<String, dynamic>);
    } catch (e) {
      throw mapDioError(e);
    }
  }

  Future<void> logout() async {
    try {
      await _api.logout();
    } catch (_) {
      // Best-effort — the local session is cleared regardless (see
      // AuthStateController.signOut), so a failed revoke call server-side
      // shouldn't block the user from signing out on-device.
    } finally {
      await _storage.clearSession();
    }
  }
}
