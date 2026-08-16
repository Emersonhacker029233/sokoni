import 'package:freezed_annotation/freezed_annotation.dart';

import 'user.dart';

part 'auth_response.freezed.dart';
part 'auth_response.g.dart';

/// Shape returned by `/auth/otp/verify` and `/auth/social` — a fresh
/// Sanctum token plus the signed-in user, not wrapped in a `data` envelope
/// (unlike single-resource GET endpoints). `isNewAccount` is true only for
/// the exact request that just created the account (`User::firstOrCreate`'s
/// `wasRecentlyCreated` / an explicit "no existing user found" check
/// server-side, see AuthController) — it drives the one-time buy/sell/
/// decide-later intent screen (CLAUDE.md Part 2 item 2), which must never
/// show for a returning user.
@freezed
abstract class AuthResponse with _$AuthResponse {
  const factory AuthResponse({
    required String token,
    required SokoniUser user,
    @JsonKey(name: 'is_new_account') @Default(false) bool isNewAccount,
  }) = _AuthResponse;

  factory AuthResponse.fromJson(Map<String, dynamic> json) => _$AuthResponseFromJson(json);
}
