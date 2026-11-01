// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'auth_session.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

_AccountUser _$AccountUserFromJson(Map<String, dynamic> json) => _AccountUser(
  id: (json['id'] as num).toInt(),
  name: json['name'] as String,
  phone: json['phone'] as String?,
  username: json['username'] as String?,
  locale: json['locale'] as String?,
);

Map<String, dynamic> _$AccountUserToJson(_AccountUser instance) =>
    <String, dynamic>{
      'id': instance.id,
      'name': instance.name,
      'phone': instance.phone,
      'username': instance.username,
      'locale': instance.locale,
    };

_AuthSession _$AuthSessionFromJson(Map<String, dynamic> json) => _AuthSession(
  token: json['token'] as String,
  user: AccountUser.fromJson(json['user'] as Map<String, dynamic>),
  isNewAccount: json['is_new_account'] as bool? ?? false,
);

Map<String, dynamic> _$AuthSessionToJson(_AuthSession instance) =>
    <String, dynamic>{
      'token': instance.token,
      'user': instance.user.toJson(),
      'is_new_account': instance.isNewAccount,
    };
