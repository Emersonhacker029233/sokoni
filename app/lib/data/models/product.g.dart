// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'product.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

_Product _$ProductFromJson(Map<String, dynamic> json) => _Product(
  id: (json['id'] as num).toInt(),
  title: json['title'] as String,
  description: json['description'] as String?,
  price: (json['price'] as num).toInt(),
  currency: json['currency'] as String? ?? 'TZS',
  stock: (json['stock'] as num?)?.toInt() ?? 0,
  condition: json['condition'] as String? ?? 'new',
  views: (json['views'] as num?)?.toInt() ?? 0,
  isActive: json['is_active'] as bool? ?? true,
  isHidden: json['is_hidden'] as bool? ?? false,
  distanceKm: (json['distance_km'] as num?)?.toDouble(),
  category: json['category'] == null
      ? null
      : SokoniCategory.fromJson(json['category'] as Map<String, dynamic>),
  seller: json['seller'] == null
      ? null
      : SellerSummary.fromJson(json['seller'] as Map<String, dynamic>),
  media:
      (json['media'] as List<dynamic>?)
          ?.map((e) => ProductMediaItem.fromJson(e as Map<String, dynamic>))
          .toList() ??
      const <ProductMediaItem>[],
  isFavorited: json['is_favorited'] as bool? ?? false,
  createdAt: json['created_at'] == null
      ? null
      : DateTime.parse(json['created_at'] as String),
);

Map<String, dynamic> _$ProductToJson(_Product instance) => <String, dynamic>{
  'id': instance.id,
  'title': instance.title,
  'description': instance.description,
  'price': instance.price,
  'currency': instance.currency,
  'stock': instance.stock,
  'condition': instance.condition,
  'views': instance.views,
  'is_active': instance.isActive,
  'is_hidden': instance.isHidden,
  'distance_km': instance.distanceKm,
  'category': instance.category?.toJson(),
  'seller': instance.seller?.toJson(),
  'media': instance.media.map((e) => e.toJson()).toList(),
  'is_favorited': instance.isFavorited,
  'created_at': instance.createdAt?.toIso8601String(),
};
