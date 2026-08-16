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
}
