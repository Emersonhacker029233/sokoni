import 'package:freezed_annotation/freezed_annotation.dart';

part 'user.freezed.dart';
part 'user.g.dart';

/// Mirrors `App\Http\Resources\UserResource` on the API.
@freezed
abstract class SokoniUser with _$SokoniUser {
  const factory SokoniUser({
    required int id,
    required String name,
    String? email,
    @JsonKey(name: 'email_verified') @Default(false) bool emailVerified,
    String? phone,
    String? avatar,
    String? locale,
    @JsonKey(name: 'is_seller') required bool isSeller,
    @JsonKey(name: 'seller_status') String? sellerStatus,
    @JsonKey(name: 'seller_handle') String? sellerHandle,
    @JsonKey(name: 'account_intent') String? accountIntent,
    @JsonKey(name: 'terms_accepted') required bool termsAccepted,
    @JsonKey(name: 'created_at') DateTime? createdAt,
  }) = _SokoniUser;

  factory SokoniUser.fromJson(Map<String, dynamic> json) => _$SokoniUserFromJson(json);
}
