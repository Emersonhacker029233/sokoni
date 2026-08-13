import 'package:freezed_annotation/freezed_annotation.dart';

part 'seller_summary.freezed.dart';
part 'seller_summary.g.dart';

/// Mirrors `App\Http\Resources\SellerSummaryResource` — the compact seller
/// embed on product listings (full shop profile is a separate model, added
/// in Phase 5).
@freezed
abstract class SellerSummary with _$SellerSummary {
  const factory SellerSummary({
    required int id,
    @JsonKey(name: 'shop_name') required String shopName,
    required String handle,
    @JsonKey(name: 'is_verified') @Default(false) bool isVerified,
    @JsonKey(name: 'rating_avg') @Default(0) double ratingAvg,
    @JsonKey(name: 'rating_count') @Default(0) int ratingCount,
    double? lat,
    double? lng,
    String? whatsapp,
  }) = _SellerSummary;

  factory SellerSummary.fromJson(Map<String, dynamic> json) => _$SellerSummaryFromJson(json);
}
