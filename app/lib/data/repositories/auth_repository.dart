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

  /// Part 5 (client feedback): every account this device currently
  /// remembers, for the profile screen's account switcher. The actual
  /// switch/sign-out operations live on AuthStateController instead of
  /// here — both need to trigger a full provider-tree restart
  /// afterwards (see main.dart's restartApp()), which this repository
  /// has no way to reach without a circular import back to core/.
  Future<List<StoredAccount>> storedAccounts() => _storage.readAccounts();

  Future<StoredAccount?> activeStoredAccount() => _storage.activeAccount();
}
