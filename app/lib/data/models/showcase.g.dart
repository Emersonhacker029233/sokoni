// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'showcase.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

_Showcase _$ShowcaseFromJson(Map<String, dynamic> json) => _Showcase(
  id: (json['id'] as num).toInt(),
  videoPath: json['video_path'] as String,
  thumbPath: json['thumb_path'] as String,
  caption: json['caption'] as String?,
  duration: (json['duration'] as num).toInt(),
  views: (json['views'] as num?)?.toInt() ?? 0,
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

Map<String, dynamic> _$ShowcaseToJson(_Showcase instance) => <String, dynamic>{
  'id': instance.id,
  'video_path': instance.videoPath,
  'thumb_path': instance.thumbPath,
  'caption': instance.caption,
  'duration': instance.duration,
  'views': instance.views,
  'seller': instance.seller?.toJson(),
  'product': instance.product?.toJson(),
  'created_at': instance.createdAt?.toIso8601String(),
};
