// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'offer.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

_Offer _$OfferFromJson(Map<String, dynamic> json) => _Offer(
  id: (json['id'] as num).toInt(),
  discountType: json['discount_type'] as String,
  discountValue: (json['discount_value'] as num).toDouble(),
  priceSnapshot: (json['price_snapshot'] as num).toDouble(),
  discountedPrice: (json['discounted_price'] as num).toInt(),
  startsAt: DateTime.parse(json['starts_at'] as String),
  endsAt: DateTime.parse(json['ends_at'] as String),
  isActive: json['is_active'] as bool? ?? true,
  seller: json['seller'] == null
      ? null
      : SellerSummary.fromJson(json['seller'] as Map<String, dynamic>),
  product: json['product'] == null
      ? null
      : ProductSummary.fromJson(json['product'] as Map<String, dynamic>),
  createdAt: json['created_at'] == null
      ? null
      : DateTime.parse(json['created_at'] as String),
);

Map<String, dynamic> _$OfferToJson(_Offer instance) => <String, dynamic>{
  'id': instance.id,
  'discount_type': instance.discountType,
  'discount_value': instance.discountValue,
  'price_snapshot': instance.priceSnapshot,
  'discounted_price': instance.discountedPrice,
  'starts_at': instance.startsAt.toIso8601String(),
  'ends_at': instance.endsAt.toIso8601String(),
  'is_active': instance.isActive,
  'seller': instance.seller?.toJson(),
  'product': instance.product?.toJson(),
  'created_at': instance.createdAt?.toIso8601String(),
};
