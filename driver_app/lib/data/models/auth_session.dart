import 'package:freezed_annotation/freezed_annotation.dart';

part 'auth_session.freezed.dart';
part 'auth_session.g.dart';

/// The subset of `App\Http\Resources\UserResource` the driver app needs.
@freezed
abstract class AccountUser with _$AccountUser {
  const factory AccountUser({
    required int id,
    required String name,
    String? phone,
    String? username,
    String? locale,
  }) = _AccountUser;

  factory AccountUser.fromJson(Map<String, dynamic> json) => _$AccountUserFromJson(json);
}

/// `/auth/otp/verify`, `/auth/login(/verify)`: a Sanctum token plus the user.
@freezed
abstract class AuthSession with _$AuthSession {
  const factory AuthSession({
    required String token,
    required AccountUser user,
    @JsonKey(name: 'is_new_account') @Default(false) bool isNewAccount,
  }) = _AuthSession;

  factory AuthSession.fromJson(Map<String, dynamic> json) => _$AuthSessionFromJson(json);
}
