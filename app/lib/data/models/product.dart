import 'package:freezed_annotation/freezed_annotation.dart';

import 'category.dart';
import 'product_media.dart';
import 'seller_summary.dart';

part 'product.freezed.dart';
part 'product.g.dart';

/// Mirrors `App\Http\Resources\ProductResource`.
@freezed
abstract class Product with _$Product {
  const factory Product({
    required int id,
    required String title,
    String? description,
    required int price,
    @Default('TZS') String currency,
    @Default(0) int stock,
    @Default('new') String condition,
    @Default(0) int views,
    @JsonKey(name: 'distance_km') double? distanceKm,
    SokoniCategory? category,
    SellerSummary? seller,
    @Default(<ProductMediaItem>[]) List<ProductMediaItem> media,
    @JsonKey(name: 'is_favorited') @Default(false) bool isFavorited,
    @JsonKey(name: 'created_at') DateTime? createdAt,
  }) = _Product;

  factory Product.fromJson(Map<String, dynamic> json) => _$ProductFromJson(json);
}

extension ProductX on Product {
  /// First image (or video thumbnail) for card display — falls back to
  /// null so callers can show a placeholder rather than crash.
  String? get coverImageUrl {
    if (media.isEmpty) return null;
    final firstImage = media.where((m) => !m.isVideo).firstOrNull;
    return (firstImage ?? media.first).thumbPath ?? (firstImage ?? media.first).path;
  }
}
