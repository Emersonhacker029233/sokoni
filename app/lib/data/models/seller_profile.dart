import 'package:freezed_annotation/freezed_annotation.dart';

import 'category.dart';

part 'seller_profile.freezed.dart';
part 'seller_profile.g.dart';

/// Mirrors `App\Http\Resources\SellerProfileResource` — the full shop
/// profile (as opposed to [SellerSummary]'s compact product-card embed).
@freezed
abstract class SellerProfile with _$SellerProfile {
  const factory SellerProfile({
    required int id,
    @JsonKey(name: 'shop_name') required String shopName,
    required String handle,
    String? bio,
    SokoniCategory? category,
    String? whatsapp,
    double? lat,
    double? lng,
    String? address,
    String? region,
    String? district,
    @Default('pending') String status,
    @JsonKey(name: 'rejection_reason') String? rejectionReason,
    @JsonKey(name: 'verified_at') DateTime? verifiedAt,
    @JsonKey(name: 'rating_avg') @Default(0) double ratingAvg,
    @JsonKey(name: 'rating_count') @Default(0) int ratingCount,
    @JsonKey(name: 'is_owner') @Default(false) bool isOwner,
    @JsonKey(name: 'created_at') DateTime? createdAt,
  }) = _SellerProfile;

  factory SellerProfile.fromJson(Map<String, dynamic> json) => _$SellerProfileFromJson(json);
}

extension SellerProfileX on SellerProfile {
  bool get isVerified => status == 'verified';
  bool get hasLocation => lat != null && lng != null;
}
