// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'review.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

_Review _$ReviewFromJson(Map<String, dynamic> json) => _Review(
  id: (json['id'] as num).toInt(),
  orderId: (json['order_id'] as num).toInt(),
  rating: (json['rating'] as num).toInt(),
  comment: json['comment'] as String?,
  reply: json['reply'] as String?,
  repliedAt: json['replied_at'] == null
      ? null
      : DateTime.parse(json['replied_at'] as String),
  buyer: json['buyer'] == null
      ? null
      : SokoniUser.fromJson(json['buyer'] as Map<String, dynamic>),
  createdAt: json['created_at'] == null
      ? null
      : DateTime.parse(json['created_at'] as String),
);

Map<String, dynamic> _$ReviewToJson(_Review instance) => <String, dynamic>{
  'id': instance.id,
  'order_id': instance.orderId,
  'rating': instance.rating,
  'comment': instance.comment,
  'reply': instance.reply,
  'replied_at': instance.repliedAt?.toIso8601String(),
  'buyer': instance.buyer?.toJson(),
  'created_at': instance.createdAt?.toIso8601String(),
};
