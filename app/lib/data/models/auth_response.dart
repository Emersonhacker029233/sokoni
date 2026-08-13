import 'package:freezed_annotation/freezed_annotation.dart';

import 'user.dart';

part 'auth_response.freezed.dart';
part 'auth_response.g.dart';

/// Shape returned by `/auth/otp/verify` and `/auth/social` — a fresh
/// Sanctum token plus the signed-in user, not wrapped in a `data` envelope
/// (unlike single-resource GET endpoints).
@freezed
abstract class AuthResponse with _$AuthResponse {
  const factory AuthResponse({required String token, required SokoniUser user}) = _AuthResponse;

  factory AuthResponse.fromJson(Map<String, dynamic> json) => _$AuthResponseFromJson(json);
}
