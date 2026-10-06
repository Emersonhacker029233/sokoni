import 'package:dio/dio.dart';

import '../../core/network/dio_client.dart';
import '../../core/storage/secure_storage.dart';
import '../api/auth_api.dart';
import '../models/auth_response.dart';
import '../models/login_challenge.dart';
import '../models/user.dart';

/// Wraps [AuthApi] with token persistence — the only repository that
/// touches [SokoniSecureStorage] directly, since every other repository
/// gets its auth handled transparently by the Dio auth interceptor.
class AuthRepository {
  AuthRepository({required AuthApi api, required SokoniSecureStorage storage, required Dio dio})
    : _api = api,
      _storage = storage,
      _dio = dio;

  final AuthApi _api;
  final SokoniSecureStorage _storage;
  // Avatar upload is multipart and goes straight through Dio — same
  // reasoning as ProductRepository's media uploads.
  final Dio _dio;

  /// [isNewAccount]: true if this phone number has never signed in
  /// before — lets the UI say plainly, right when the code is sent,
  /// that a new account is about to be created (CLAUDE.md Part 2 item
  /// 1). [expiresAt]: Part 3 (client feedback) — the server's own real
  /// expiry instant for this code, not a client-guessed duration, so
  /// the resend screen can show exactly when/why it stops working.
  Future<({bool isNewAccount, DateTime expiresAt})> requestOtp(String phoneE164) async {
    try {
      final json = await _api.requestOtp({'phone': phoneE164});
      return (
        isNewAccount: json['is_new_account'] as bool? ?? false,
        expiresAt: DateTime.parse(json['expires_at'] as String),
      );
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
      await _persistAccount(response);
      return response;
    } catch (e) {
      throw mapDioError(e);
    }
  }

  /// Check-only — no OTP is sent. Used by the "Create an account" flow's
  /// details step to catch an already-registered number while the user
  /// can still do something about it, rather than at the final verify
  /// step after filling in everything else.
  Future<bool> checkPhoneExists(String phoneE164) async {
    try {
      final json = await _api.checkPhone({'phone': phoneE164});
      return json['exists'] as bool? ?? false;
    } catch (e) {
      throw mapDioError(e);
    }
  }

  /// Verifies the OTP and creates the account (and, for a seller, the
  /// SellerProfile) in one request — the "Create an account" flow's final
  /// step (CLAUDE.md restructure, 2026-08-25). The seller-only fields are
  /// all required together server-side when [accountIntent] is `'sell'`
  /// (see `RegisterAccountRequest`) — pass all of them or none.
  Future<AuthResponse> register({
    required String phoneE164,
    required String code,
    required String name,
    required String username,
    required String password,
    required String accountIntent,
    required String termsVersion,
    String? email,
    bool marketingConsent = false,
    String? shopName,
    String? handle,
    int? categoryId,
    String? region,
    String? district,
    String? address,
    String? whatsappE164,
  }) async {
    try {
      final response = await _api.register({
        'phone': phoneE164,
        'code': code,
        'name': name,
        'username': username,
        'password': password,
        'password_confirmation': password,
        'email': ?email,
        'marketing_consent': marketingConsent,
        'account_intent': accountIntent,
        'terms_version': termsVersion,
        'shop_name': ?shopName,
        'handle': ?handle,
        'category_id': ?categoryId,
        'region': ?region,
        'district': ?district,
        'address': ?address,
        'whatsapp': ?whatsappE164,
      });
      await _persistAccount(response);
      return response;
    } catch (e) {
      throw mapDioError(e);
    }
  }

  Future<AuthResponse> socialLogin({required String provider, required String token}) async {
    try {
      final response = await _api.socialLogin({'provider': provider, 'token': token});
      await _persistAccount(response);
      return response;
    } catch (e) {
      throw mapDioError(e);
    }
  }

  /// Username/password rework (CLAUDE.md Part D) — live availability
  /// check while typing.
  Future<bool> checkUsernameAvailable(String username) async {
    try {
      final json = await _api.checkUsername({'username': username});
      return json['available'] as bool? ?? false;
    } catch (e) {
      throw mapDioError(e);
    }
  }

  /// Step 1 of sign-in (CLAUDE.md 2.2) — looks up a remembered
  /// "recognised device" token for this exact login string first, so a
  /// correct password on a device that's already completed the SMS step
  /// once can sign in without it again.
  Future<LoginChallenge> login({required String login, required String password}) async {
    try {
      final deviceToken = await _storage.readDeviceToken(login);
      final json = await _api.login({
        'login': login,
        'password': password,
        'device_token': ?deviceToken,
      });

      if (json['requires_code'] == true) {
        return LoginChallenge.requiresCode(DateTime.parse(json['expires_at'] as String));
      }

      final response = AuthResponse.fromJson(json as Map<String, dynamic>);
      await _persistAccount(response);

      return LoginChallenge.signedIn(response);
    } catch (e) {
      throw mapDioError(e);
    }
  }

  /// Step 2 — the SMS code. Stores the fresh "recognised device" token
  /// against this exact login string, so the next `login()` call for it
  /// can skip this step.
  Future<AuthResponse> verifyLogin({required String login, required String code}) async {
    try {
      final json = await _api.verifyLogin({'login': login, 'code': code});
      final response = AuthResponse.fromJson(json as Map<String, dynamic>);
      await _persistAccount(response);
      if (json['device_token'] is String) {
        await _storage.writeDeviceToken(login, json['device_token'] as String);
      }

      return response;
    } catch (e) {
      throw mapDioError(e);
    }
  }

  /// CLAUDE.md 2.6 — an existing account's one-time upgrade to a
  /// username+password, or a new account's first-time setup.
  Future<SokoniUser> setCredentials({required String username, required String password}) async {
    try {
      final json = await _api.setCredentials({
        'username': username,
        'password': password,
        'password_confirmation': password,
      });
      return SokoniUser.fromJson((json as Map<String, dynamic>)['data'] as Map<String, dynamic>);
    } catch (e) {
      throw mapDioError(e);
    }
  }

  /// Settings' "Require a code every time I sign in" toggle (CLAUDE.md 2.4).
  Future<SokoniUser> updateTwoFactor(bool enabled) async {
    try {
      final json = await _api.updateTwoFactor({'enabled': enabled});
      return SokoniUser.fromJson((json as Map<String, dynamic>)['data'] as Map<String, dynamic>);
    } catch (e) {
      throw mapDioError(e);
    }
  }

  /// CLAUDE.md 2.5, step 1 — always resolves the same way regardless of
  /// whether the account exists; the server's response is deliberately
  /// identical either way.
  Future<void> forgotPasswordRequest(String login) async {
    try {
      await _api.forgotPasswordRequest({'login': login});
    } catch (e) {
      throw mapDioError(e);
    }
  }

  /// Step 2 — code + new password together. A success here signs this
  /// device in fresh and (server-side) revokes every other session.
  Future<AuthResponse> forgotPasswordReset({required String login, required String code, required String password}) async {
    try {
      final json = await _api.forgotPasswordReset({
        'login': login,
        'code': code,
        'password': password,
        'password_confirmation': password,
      });
      final response = AuthResponse.fromJson(json as Map<String, dynamic>);
      await _persistAccount(response);
      if (json['device_token'] is String) {
        await _storage.writeDeviceToken(login, json['device_token'] as String);
      }

      return response;
    } catch (e) {
      throw mapDioError(e);
    }
  }

  /// Part 5 (client feedback): every real sign-in path (OTP, register,
  /// social) lands here — one place that turns a fresh [AuthResponse]
  /// into a remembered [StoredAccount] and makes it active, rather than
  /// three copies of the same two-write sequence that could drift apart.
  Future<void> _persistAccount(AuthResponse response) {
    return _storage.addOrUpdateAccount(
      StoredAccount(
        userId: response.user.id,
        token: response.token,
        name: response.user.name,
        avatar: response.user.avatar,
        handle: response.user.sellerHandle,
      ),
    );
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

  /// Profile settings (C5) — name/email. A changed email resets
  /// verification server-side and re-sends the link automatically; passing
  /// `email: null` clears it (an email is always optional).
  Future<SokoniUser> updateProfile({required String name, String? email}) async {
    try {
      final json = await _api.updateProfile({'name': name, 'email': email});
      return SokoniUser.fromJson(json['data'] as Map<String, dynamic>);
    } catch (e) {
      throw mapDioError(e);
    }
  }

  Future<void> resendVerificationEmail() async {
    try {
      await _api.resendVerificationEmail();
    } catch (e) {
      throw mapDioError(e);
    }
  }

  /// Part 4 (client feedback): Settings' Profile section — photo
  /// upload/change. [onProgress] mirrors ProductRepository's own media
  /// upload progress callback (0.0-1.0, bytes actually sent).
  Future<SokoniUser> uploadAvatar({required String imagePath, void Function(double progress)? onProgress}) async {
    try {
      final response = await _dio.post(
        '/auth/avatar',
        data: FormData.fromMap({'avatar': await MultipartFile.fromFile(imagePath)}),
        onSendProgress: onProgress == null ? null : (sent, total) => onProgress(total > 0 ? sent / total : 0),
      );
      return SokoniUser.fromJson((response.data as Map<String, dynamic>)['data'] as Map<String, dynamic>);
    } catch (e) {
      throw mapDioError(e);
    }
  }

  Future<SokoniUser> removeAvatar() async {
    try {
      final json = await _api.removeAvatar();
      return SokoniUser.fromJson((json as Map<String, dynamic>)['data'] as Map<String, dynamic>);
    } catch (e) {
      throw mapDioError(e);
    }
  }

  /// Part 4 (client feedback): Settings' Notifications section — every
  /// argument is optional so a single toggle flip only ever sends that
  /// one field, matching UpdateNotificationPreferencesRequest server-side.
  Future<SokoniUser> updateNotificationPreferences({
    bool? notifyOrders,
    bool? notifyMessages,
    bool? notifyOffers,
    bool? notifyMarketing,
  }) async {
    try {
      final json = await _api.updateNotificationPreferences({
        'notify_orders': ?notifyOrders,
        'notify_messages': ?notifyMessages,
        'notify_offers': ?notifyOffers,
        'notify_marketing': ?notifyMarketing,
      });
      return SokoniUser.fromJson((json as Map<String, dynamic>)['data'] as Map<String, dynamic>);
    } catch (e) {
      throw mapDioError(e);
    }
  }

  /// Part 4 (client feedback): "phone number ... changing it needs
  /// re-verification" — step 1, sends an OTP to the new number.
  /// [expiresAt]: same real-server-timestamp pattern as [requestOtp].
  Future<DateTime> requestPhoneChange(String newPhoneE164) async {
    try {
      final json = await _api.requestPhoneChange({'phone': newPhoneE164});
      return DateTime.parse((json as Map<String, dynamic>)['expires_at'] as String);
    } catch (e) {
      throw mapDioError(e);
    }
  }

  /// Step 2 — verifies the OTP and applies the new number.
  Future<SokoniUser> verifyPhoneChange({required String newPhoneE164, required String code}) async {
    try {
      final json = await _api.verifyPhoneChange({'phone': newPhoneE164, 'code': code});
      return SokoniUser.fromJson((json as Map<String, dynamic>)['data'] as Map<String, dynamic>);
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

  /// Apple Guideline 5.1.1(v): account creation happens in-app, so
  /// deletion must too. Unlike [logout] above, a failed server call is
  /// never swallowed here — clearing the local session on a failure would
  /// tell the user their account is gone when it still exists server-side.
  Future<void> deleteAccount() async {
    try {
      await _api.deleteAccount();
    } catch (e) {
      throw mapDioError(e);
    }
    await _storage.clearSession();
  }

  /// Part 5 (client feedback): every account this device currently
  /// remembers, for the profile screen's account switcher. The actual
  /// switch/sign-out operations live on AuthStateController instead of
  /// here — both need to trigger a full provider-tree restart
  /// afterwards (see main.dart's restartApp()), which this repository
  /// has no way to reach without a circular import back to core/.
  Future<List<StoredAccount>> storedAccounts() => _storage.readAccounts();

  Future<StoredAccount?> activeStoredAccount() => _storage.activeAccount();
}
