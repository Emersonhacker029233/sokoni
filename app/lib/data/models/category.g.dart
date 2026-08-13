// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'category.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

_SokoniCategory _$SokoniCategoryFromJson(Map<String, dynamic> json) =>
    _SokoniCategory(
      id: (json['id'] as num).toInt(),
      parentId: (json['parent_id'] as num?)?.toInt(),
      nameEn: json['name_en'] as String,
      nameSw: json['name_sw'] as String,
      icon: json['icon'] as String?,
      sortOrder: (json['sort_order'] as num?)?.toInt() ?? 0,
    );

Map<String, dynamic> _$SokoniCategoryToJson(_SokoniCategory instance) =>
    <String, dynamic>{
      'id': instance.id,
      'parent_id': instance.parentId,
      'name_en': instance.nameEn,
      'name_sw': instance.nameSw,
      'icon': instance.icon,
      'sort_order': instance.sortOrder,
    };
