// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'product_media.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

_ProductMediaItem _$ProductMediaItemFromJson(Map<String, dynamic> json) =>
    _ProductMediaItem(
      id: (json['id'] as num).toInt(),
      type: json['type'] as String,
      path: json['path'] as String,
      thumbPath: json['thumb_path'] as String?,
      cardPath: json['card_path'] as String?,
      duration: (json['duration'] as num?)?.toInt(),
      sort: (json['sort'] as num?)?.toInt() ?? 0,
    );

Map<String, dynamic> _$ProductMediaItemToJson(_ProductMediaItem instance) =>
    <String, dynamic>{
      'id': instance.id,
      'type': instance.type,
      'path': instance.path,
      'thumb_path': instance.thumbPath,
      'card_path': instance.cardPath,
      'duration': instance.duration,
      'sort': instance.sort,
    };
