import '../../core/network/dio_client.dart';
import '../../core/storage/secure_storage.dart';
import '../api/auth_api.dart';
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

  Future<void> requestOtp(String phoneE164) async {
    try {
      await _api.requestOtp({'phone': phoneE164});
    } catch (e) {
      throw mapDioError(e);
    }
  }

  Future<SokoniUser> verifyOtp({required String phoneE164, required String code, String? name}) async {
    try {
      final response = await _api.verifyOtp({
        'phone': phoneE164,
        'code': code,
        'name': name,
      });
      await _storage.writeToken(response.token);
      await _storage.writeUserId(response.user.id);
      return response.user;
    } catch (e) {
      throw mapDioError(e);
    }
  }

  Future<SokoniUser> socialLogin({required String provider, required String token}) async {
    try {
      final response = await _api.socialLogin({'provider': provider, 'token': token});
      await _storage.writeToken(response.token);
      await _storage.writeUserId(response.user.id);
      return response.user;
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
