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
    String? username,
    // Part D (username/password rework): true for any account that
    // hasn't set a password yet — drives the one-time upgrade prompt
    // shown after an old-flow (phone+code) sign-in.
    @JsonKey(name: 'needs_credential_setup') @Default(false) bool needsCredentialSetup,
    @JsonKey(name: 'two_factor_enabled') @Default(false) bool twoFactorEnabled,
    String? avatar,
    String? locale,
    @JsonKey(name: 'is_seller') required bool isSeller,
    @JsonKey(name: 'seller_status') String? sellerStatus,
    @JsonKey(name: 'seller_handle') String? sellerHandle,
    @JsonKey(name: 'account_intent') String? accountIntent,
    @JsonKey(name: 'terms_accepted') required bool termsAccepted,
    @JsonKey(name: 'created_at') DateTime? createdAt,
    // Part 4 (client feedback): Settings' Notifications section.
    @JsonKey(name: 'notify_orders') @Default(true) bool notifyOrders,
    @JsonKey(name: 'notify_messages') @Default(true) bool notifyMessages,
    @JsonKey(name: 'notify_offers') @Default(true) bool notifyOffers,
    @JsonKey(name: 'notify_marketing') @Default(false) bool notifyMarketing,
  }) = _SokoniUser;

  factory SokoniUser.fromJson(Map<String, dynamic> json) => _$SokoniUserFromJson(json);
}
