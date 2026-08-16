// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'social_update.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

_SocialUpdate _$SocialUpdateFromJson(Map<String, dynamic> json) =>
    _SocialUpdate(
      id: (json['id'] as num).toInt(),
      type: json['type'] as String,
      mediaPath: json['media_path'] as String,
      thumbPath: json['thumb_path'] as String?,
      caption: json['caption'] as String?,
      seller: json['seller'] == null
          ? null
          : SellerSummary.fromJson(json['seller'] as Map<String, dynamic>),
      product: json['product'] == null
          ? null
          : ProductSummary.fromJson(json['product'] as Map<String, dynamic>),
      expiresAt: DateTime.parse(json['expires_at'] as String),
      createdAt: json['created_at'] == null
          ? null
          : DateTime.parse(json['created_at'] as String),
    );

Map<String, dynamic> _$SocialUpdateToJson(_SocialUpdate instance) =>
    <String, dynamic>{
      'id': instance.id,
      'type': instance.type,
      'media_path': instance.mediaPath,
      'thumb_path': instance.thumbPath,
      'caption': instance.caption,
      'seller': instance.seller?.toJson(),
      'product': instance.product?.toJson(),
      'expires_at': instance.expiresAt.toIso8601String(),
      'created_at': instance.createdAt?.toIso8601String(),
    };
