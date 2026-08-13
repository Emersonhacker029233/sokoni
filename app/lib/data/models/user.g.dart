// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'user.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

_SokoniUser _$SokoniUserFromJson(Map<String, dynamic> json) => _SokoniUser(
  id: (json['id'] as num).toInt(),
  name: json['name'] as String,
  email: json['email'] as String?,
  phone: json['phone'] as String?,
  avatar: json['avatar'] as String?,
  locale: json['locale'] as String?,
  isSeller: json['is_seller'] as bool,
  sellerStatus: json['seller_status'] as String?,
  termsAccepted: json['terms_accepted'] as bool,
  createdAt: json['created_at'] == null
      ? null
      : DateTime.parse(json['created_at'] as String),
);

Map<String, dynamic> _$SokoniUserToJson(_SokoniUser instance) =>
    <String, dynamic>{
      'id': instance.id,
      'name': instance.name,
      'email': instance.email,
      'phone': instance.phone,
      'avatar': instance.avatar,
      'locale': instance.locale,
      'is_seller': instance.isSeller,
      'seller_status': instance.sellerStatus,
      'terms_accepted': instance.termsAccepted,
      'created_at': instance.createdAt?.toIso8601String(),
    };
