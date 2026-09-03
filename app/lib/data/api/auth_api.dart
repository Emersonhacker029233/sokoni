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

  @POST('/auth/terms/accept')
  Future<dynamic> acceptTerms(@Body() Map<String, dynamic> body);

  /// Profile settings (C5): name/email, from the app's own Settings screen.
  @PATCH('/auth/profile')
  Future<dynamic> updateProfile(@Body() Map<String, dynamic> body);

  /// Asks for the verification link again — shown only while the user's
  /// email is set but unverified.
  @POST('/auth/email/resend')
  Future<dynamic> resendVerificationEmail();
}
