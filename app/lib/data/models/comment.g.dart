// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'comment.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

_ProductComment _$ProductCommentFromJson(Map<String, dynamic> json) =>
    _ProductComment(
      id: (json['id'] as num).toInt(),
      productId: (json['product_id'] as num).toInt(),
      parentId: (json['parent_id'] as num?)?.toInt(),
      body: json['body'] as String,
      user: CommentAuthor.fromJson(json['user'] as Map<String, dynamic>),
      isFromSeller: json['is_from_seller'] as bool? ?? false,
      replies:
          (json['replies'] as List<dynamic>?)
              ?.map((e) => ProductComment.fromJson(e as Map<String, dynamic>))
              .toList() ??
          const <ProductComment>[],
      createdAt: json['created_at'] == null
          ? null
          : DateTime.parse(json['created_at'] as String),
    );

Map<String, dynamic> _$ProductCommentToJson(_ProductComment instance) =>
    <String, dynamic>{
      'id': instance.id,
      'product_id': instance.productId,
      'parent_id': instance.parentId,
      'body': instance.body,
      'user': instance.user.toJson(),
      'is_from_seller': instance.isFromSeller,
      'replies': instance.replies.map((e) => e.toJson()).toList(),
      'created_at': instance.createdAt?.toIso8601String(),
    };

_CommentAuthor _$CommentAuthorFromJson(Map<String, dynamic> json) =>
    _CommentAuthor(
      id: (json['id'] as num).toInt(),
      name: json['name'] as String,
      avatar: json['avatar'] as String?,
    );

Map<String, dynamic> _$CommentAuthorToJson(_CommentAuthor instance) =>
    <String, dynamic>{
      'id': instance.id,
      'name': instance.name,
      'avatar': instance.avatar,
    };
