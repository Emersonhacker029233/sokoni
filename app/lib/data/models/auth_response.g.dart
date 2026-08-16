// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'auth_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

_AuthResponse _$AuthResponseFromJson(Map<String, dynamic> json) =>
    _AuthResponse(
      token: json['token'] as String,
      user: SokoniUser.fromJson(json['user'] as Map<String, dynamic>),
      isNewAccount: json['is_new_account'] as bool? ?? false,
    );

Map<String, dynamic> _$AuthResponseToJson(_AuthResponse instance) =>
    <String, dynamic>{
      'token': instance.token,
      'user': instance.user.toJson(),
      'is_new_account': instance.isNewAccount,
    };
