// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'seller_summary.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

_SellerSummary _$SellerSummaryFromJson(Map<String, dynamic> json) =>
    _SellerSummary(
      id: (json['id'] as num).toInt(),
      shopName: json['shop_name'] as String,
      logo: json['logo'] as String?,
      handle: json['handle'] as String,
      isVerified: json['is_verified'] as bool? ?? false,
      ratingAvg: (json['rating_avg'] as num?)?.toDouble() ?? 0,
      ratingCount: (json['rating_count'] as num?)?.toInt() ?? 0,
      lat: (json['lat'] as num?)?.toDouble(),
      lng: (json['lng'] as num?)?.toDouble(),
      whatsapp: json['whatsapp'] as String?,
      isFollowing: json['is_following'] as bool? ?? false,
    );

Map<String, dynamic> _$SellerSummaryToJson(_SellerSummary instance) =>
    <String, dynamic>{
      'id': instance.id,
      'shop_name': instance.shopName,
      'logo': instance.logo,
      'handle': instance.handle,
      'is_verified': instance.isVerified,
      'rating_avg': instance.ratingAvg,
      'rating_count': instance.ratingCount,
      'lat': instance.lat,
      'lng': instance.lng,
      'whatsapp': instance.whatsapp,
      'is_following': instance.isFollowing,
    };
