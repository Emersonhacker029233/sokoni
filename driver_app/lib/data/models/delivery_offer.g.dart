// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'delivery_offer.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

_DeliveryOffer _$DeliveryOfferFromJson(Map<String, dynamic> json) =>
    _DeliveryOffer(
      jobId: (json['job_id'] as num).toInt(),
      offeredAt: DateTime.parse(json['offered_at'] as String),
      wave: (json['wave'] as num?)?.toInt() ?? 1,
      distanceToShopKm: (json['distance_to_shop_km'] as num?)?.toDouble() ?? 0,
      tripKm: (json['trip_km'] as num?)?.toDouble() ?? 0,
      driverEarnings: (json['driver_earnings'] as num).toInt(),
      fee: (json['fee'] as num).toInt(),
      collectCash: (json['collect_cash'] as num?)?.toInt() ?? 0,
      itemsCount: (json['items_count'] as num?)?.toInt() ?? 0,
      shop: OfferShop.fromJson(json['shop'] as Map<String, dynamic>),
      dropoffArea: LatLngJson.fromJson(
        json['dropoff_area'] as Map<String, dynamic>,
      ),
    );

Map<String, dynamic> _$DeliveryOfferToJson(_DeliveryOffer instance) =>
    <String, dynamic>{
      'job_id': instance.jobId,
      'offered_at': instance.offeredAt.toIso8601String(),
      'wave': instance.wave,
      'distance_to_shop_km': instance.distanceToShopKm,
      'trip_km': instance.tripKm,
      'driver_earnings': instance.driverEarnings,
      'fee': instance.fee,
      'collect_cash': instance.collectCash,
      'items_count': instance.itemsCount,
      'shop': instance.shop.toJson(),
      'dropoff_area': instance.dropoffArea.toJson(),
    };

_OfferShop _$OfferShopFromJson(Map<String, dynamic> json) => _OfferShop(
  name: json['name'] as String?,
  district: json['district'] as String?,
  lat: (json['lat'] as num).toDouble(),
  lng: (json['lng'] as num).toDouble(),
);

Map<String, dynamic> _$OfferShopToJson(_OfferShop instance) =>
    <String, dynamic>{
      'name': instance.name,
      'district': instance.district,
      'lat': instance.lat,
      'lng': instance.lng,
    };

_LatLngJson _$LatLngJsonFromJson(Map<String, dynamic> json) => _LatLngJson(
  lat: (json['lat'] as num).toDouble(),
  lng: (json['lng'] as num).toDouble(),
);

Map<String, dynamic> _$LatLngJsonToJson(_LatLngJson instance) =>
    <String, dynamic>{'lat': instance.lat, 'lng': instance.lng};
