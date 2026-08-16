import 'package:freezed_annotation/freezed_annotation.dart';

part 'product_summary.freezed.dart';
part 'product_summary.g.dart';

/// Mirrors `App\Http\Resources\ProductSummaryResource` — the compact
/// product embed on Update/Offer/Showcase (full [Product] carries its
/// whole media/category/seller payload, too heavy to repeat per item in a
/// Showcase feed).
@freezed
abstract class ProductSummary with _$ProductSummary {
  const factory ProductSummary({
    required int id,
    required String title,
    required int price,
    @Default('TZS') String currency,
    @JsonKey(name: 'cover_image_url') String? coverImageUrl,
  }) = _ProductSummary;

  factory ProductSummary.fromJson(Map<String, dynamic> json) => _$ProductSummaryFromJson(json);
}
