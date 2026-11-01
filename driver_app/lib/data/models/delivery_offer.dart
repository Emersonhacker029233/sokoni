import 'package:freezed_annotation/freezed_annotation.dart';

part 'delivery_offer.freezed.dart';
part 'delivery_offer.g.dart';

/// Mirrors `DeliveryOfferResource`: what a driver sees before accepting.
/// No customer name, phone or exact address.
@freezed
abstract class DeliveryOffer with _$DeliveryOffer {
  const factory DeliveryOffer({
    @JsonKey(name: 'job_id') required int jobId,
    @JsonKey(name: 'offered_at') required DateTime offeredAt,
    @Default(1) int wave,
    @JsonKey(name: 'distance_to_shop_km') @Default(0) double distanceToShopKm,
    @JsonKey(name: 'trip_km') @Default(0) double tripKm,
    @JsonKey(name: 'driver_earnings') required int driverEarnings,
    required int fee,
    @JsonKey(name: 'collect_cash') @Default(0) int collectCash,
    @JsonKey(name: 'items_count') @Default(0) int itemsCount,
    required OfferShop shop,
    @JsonKey(name: 'dropoff_area') required LatLngJson dropoffArea,
  }) = _DeliveryOffer;

  factory DeliveryOffer.fromJson(Map<String, dynamic> json) => _$DeliveryOfferFromJson(json);
}

@freezed
abstract class OfferShop with _$OfferShop {
  const factory OfferShop({
    String? name,
    String? district,
    required double lat,
    required double lng,
  }) = _OfferShop;

  factory OfferShop.fromJson(Map<String, dynamic> json) => _$OfferShopFromJson(json);
}

@freezed
abstract class LatLngJson with _$LatLngJson {
  const factory LatLngJson({required double lat, required double lng}) = _LatLngJson;

  factory LatLngJson.fromJson(Map<String, dynamic> json) => _$LatLngJsonFromJson(json);
}
