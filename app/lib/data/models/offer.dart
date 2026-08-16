import 'package:freezed_annotation/freezed_annotation.dart';

import 'product_summary.dart';
import 'seller_summary.dart';

part 'offer.freezed.dart';
part 'offer.g.dart';

/// "Offer"/"Punguzo" (CLAUDE.md Part 3) — a time-limited deal on a Listing.
/// Mirrors `App\Http\Resources\OfferResource`.
@freezed
abstract class Offer with _$Offer {
  const factory Offer({
    required int id,
    @JsonKey(name: 'discount_type') required String discountType, // 'percent' | 'fixed_price'
    @JsonKey(name: 'discount_value') required double discountValue,
    @JsonKey(name: 'price_snapshot') required double priceSnapshot,
    @JsonKey(name: 'discounted_price') required int discountedPrice,
    @JsonKey(name: 'starts_at') required DateTime startsAt,
    @JsonKey(name: 'ends_at') required DateTime endsAt,
    @JsonKey(name: 'is_active') @Default(true) bool isActive,
    SellerSummary? seller,
    ProductSummary? product,
    @JsonKey(name: 'created_at') DateTime? createdAt,
  }) = _Offer;

  factory Offer.fromJson(Map<String, dynamic> json) => _$OfferFromJson(json);
}

extension OfferX on Offer {
  Duration get timeRemaining => endsAt.difference(DateTime.now());
}
