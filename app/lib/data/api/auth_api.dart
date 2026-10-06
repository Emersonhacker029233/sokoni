import 'package:dio/dio.dart' hide Headers;
import 'package:retrofit/retrofit.dart';

import '../models/auth_response.dart';

part 'auth_api.g.dart';

@RestApi()
abstract class AuthApi {
  factory AuthApi(Dio dio, {String baseUrl}) = _AuthApi;

  /// Raw envelope, not a typed model — same reasoning as [me]: this is
  /// just `{message, is_new_account}`, not worth a dedicated model class.
  @POST('/auth/otp/request')
  Future<dynamic> requestOtp(@Body() Map<String, dynamic> body);

  @POST('/auth/otp/verify')
  Future<AuthResponse> verifyOtp(@Body() Map<String, dynamic> body);

  /// Check-only — no OTP side effect. Used by the "Create an account"
  /// flow's details step to catch an already-registered number before the
  /// final verify step, per CLAUDE.md restructure (2026-08-25): "validate
  /// as you go, not at the end".
  @POST('/auth/check-phone')
  Future<dynamic> checkPhone(@Body() Map<String, dynamic> body);

  /// Verifies the OTP and creates the account (and, for a seller, the
  /// SellerProfile) in one request — the "Create an account" flow's final
  /// step. Distinct from [verifyOtp], which backs the separate "Sign in"
  /// flow and never creates a seller profile.
  @POST('/auth/register')
  Future<AuthResponse> register(@Body() Map<String, dynamic> body);

  @POST('/auth/social')
  Future<AuthResponse> socialLogin(@Body() Map<String, dynamic> body);

  /// Username/password rework (CLAUDE.md Part D) — live availability
  /// check while typing.
  @POST('/auth/username/check')
  Future<dynamic> checkUsername(@Body() Map<String, dynamic> body);

  /// Step 1 of sign-in: username-or-phone + password. Returns either a
  /// full `AuthResponse`-shaped body (recognised device, no 2FA) or
  /// `{requires_code: true, expires_at}` — `dynamic`, not `AuthResponse`,
  /// since the shape genuinely varies; AuthRepository branches on it.
  @POST('/auth/login')
  Future<dynamic> login(@Body() Map<String, dynamic> body);

  /// Step 2 — the SMS code. Response also carries `device_token`
  /// (`AuthResponse` itself doesn't model that field — see AuthRepository).
  @POST('/auth/login/verify')
  Future<dynamic> verifyLogin(@Body() Map<String, dynamic> body);

  /// CLAUDE.md 2.6 — an existing account's one-time upgrade, or a new
  /// account's first-time credential setup.
  @POST('/auth/credentials')
  Future<dynamic> setCredentials(@Body() Map<String, dynamic> body);

  /// Settings' "Require a code every time I sign in" toggle.
  @PATCH('/auth/two-factor')
  Future<dynamic> updateTwoFactor(@Body() Map<String, dynamic> body);

  /// CLAUDE.md 2.5, step 1 — always the same response regardless of
  /// whether the account exists.
  @POST('/auth/forgot-password/request')
  Future<dynamic> forgotPasswordRequest(@Body() Map<String, dynamic> body);

  /// Step 2 — code + new password in one request.
  @POST('/auth/forgot-password/reset')
  Future<dynamic> forgotPasswordReset(@Body() Map<String, dynamic> body);

  @POST('/auth/intent')
  Future<dynamic> updateIntent(@Body() Map<String, dynamic> body);

  /// Raw envelope (`{"data": {...}}`) — unwrapped by AuthRepository so this
  /// layer doesn't need a generic envelope model for every single resource.
  /// Typed as `dynamic`, not `Map<String, dynamic>`: retrofit_generator
  /// mis-generates a `dynamic.fromJson(...)` call for the latter.
  @GET('/auth/me')
  Future<dynamic> me();

  @POST('/auth/logout')
  Future<void> logout();

  /// Apple Guideline 5.1.1(v): account creation happens in-app, so
  /// deletion must too — not by email, not through a website.
  @DELETE('/auth/me')
  Future<void> deleteAccount();

  @POST('/auth/terms/accept')
  Future<dynamic> acceptTerms(@Body() Map<String, dynamic> body);

  /// Profile settings (C5): name/email, from the app's own Settings screen.
  @PATCH('/auth/profile')
  Future<dynamic> updateProfile(@Body() Map<String, dynamic> body);

  /// Asks for the verification link again — shown only while the user's
  /// email is set but unverified.
  @POST('/auth/email/resend')
  Future<dynamic> resendVerificationEmail();

  /// Part 4 (client feedback): Settings' "remove" action — the upload
  /// side goes through raw Dio in AuthRepository (multipart), same
  /// reasoning as ProductRepository's media uploads.
  @DELETE('/auth/avatar')
  Future<dynamic> removeAvatar();

  /// Part 4 (client feedback): Settings' Notifications section. Every
  /// field optional — the client only ever sends the one toggle just
  /// flipped, per UpdateNotificationPreferencesRequest.
  @PATCH('/auth/notification-preferences')
  Future<dynamic> updateNotificationPreferences(@Body() Map<String, dynamic> body);

  /// Part 4 (client feedback): "phone number ... changing it needs
  /// re-verification" — step 1, sends an OTP to the new number.
  @POST('/auth/phone/change/request')
  Future<dynamic> requestPhoneChange(@Body() Map<String, dynamic> body);

  /// Step 2 — verifies the OTP and applies the new number.
  @POST('/auth/phone/change/verify')
  Future<dynamic> verifyPhoneChange(@Body() Map<String, dynamic> body);
}
