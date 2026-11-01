import 'package:dio/dio.dart' hide Headers;
import 'package:retrofit/retrofit.dart';

part 'auth_api.g.dart';

/// The existing Sokoni `/auth/*` routes. Drivers are ordinary Sokoni
/// accounts, so there is no separate driver sign-in.
///
/// Bodies are typed `dynamic`: several endpoints answer with more than one
/// shape (`/auth/login` may ask for an SMS code), so AuthRepository parses.
@RestApi()
abstract class AuthApi {
  factory AuthApi(Dio dio, {String baseUrl}) = _AuthApi;

  @POST('/auth/otp/request')
  Future<dynamic> requestOtp(@Body() Map<String, dynamic> body);

  @POST('/auth/otp/verify')
  Future<dynamic> verifyOtp(@Body() Map<String, dynamic> body);

  @POST('/auth/login')
  Future<dynamic> login(@Body() Map<String, dynamic> body);

  @POST('/auth/login/verify')
  Future<dynamic> verifyLogin(@Body() Map<String, dynamic> body);

  @GET('/auth/me')
  Future<dynamic> me();

  @POST('/auth/logout')
  Future<void> logout();

  /// App Store Guideline 5.1.1(v): accounts made in-app can be deleted in-app.
  @DELETE('/auth/me')
  Future<void> deleteAccount();
}
