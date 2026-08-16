// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'product_summary.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

_ProductSummary _$ProductSummaryFromJson(Map<String, dynamic> json) =>
    _ProductSummary(
      id: (json['id'] as num).toInt(),
      title: json['title'] as String,
      price: (json['price'] as num).toInt(),
      currency: json['currency'] as String? ?? 'TZS',
      coverImageUrl: json['cover_image_url'] as String?,
    );

Map<String, dynamic> _$ProductSummaryToJson(_ProductSummary instance) =>
    <String, dynamic>{
      'id': instance.id,
      'title': instance.title,
      'price': instance.price,
      'currency': instance.currency,
      'cover_image_url': instance.coverImageUrl,
    };
