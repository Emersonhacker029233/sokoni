// GENERATED CODE - DO NOT MODIFY BY HAND
// coverage:ignore-file
// ignore_for_file: type=lint
// ignore_for_file: unused_element, deprecated_member_use, deprecated_member_use_from_same_package, use_function_type_syntax_for_parameters, unnecessary_const, avoid_init_to_null, invalid_override_different_default_values_named, prefer_expression_function_bodies, annotate_overrides, invalid_annotation_target, unnecessary_question_mark

part of 'auth_session.dart';

// **************************************************************************
// FreezedGenerator
// **************************************************************************

// dart format off
T _$identity<T>(T value) => value;

/// @nodoc
mixin _$AccountUser {

 int get id; String get name; String? get phone; String? get username; String? get locale;
/// Create a copy of AccountUser
/// with the given fields replaced by the non-null parameter values.
@JsonKey(includeFromJson: false, includeToJson: false)
@pragma('vm:prefer-inline')
$AccountUserCopyWith<AccountUser> get copyWith => _$AccountUserCopyWithImpl<AccountUser>(this as AccountUser, _$identity);

  /// Serializes this AccountUser to a JSON map.
  Map<String, dynamic> toJson();


@override
bool operator ==(Object other) {
  return identical(this, other) || (other.runtimeType == runtimeType&&other is AccountUser&&(identical(other.id, id) || other.id == id)&&(identical(other.name, name) || other.name == name)&&(identical(other.phone, phone) || other.phone == phone)&&(identical(other.username, username) || other.username == username)&&(identical(other.locale, locale) || other.locale == locale));
}

@JsonKey(includeFromJson: false, includeToJson: false)
@override
int get hashCode => Object.hash(runtimeType,id,name,phone,username,locale);

@override
String toString() {
  return 'AccountUser(id: $id, name: $name, phone: $phone, username: $username, locale: $locale)';
}


}

/// @nodoc
abstract mixin class $AccountUserCopyWith<$Res>  {
  factory $AccountUserCopyWith(AccountUser value, $Res Function(AccountUser) _then) = _$AccountUserCopyWithImpl;
@useResult
$Res call({
 int id, String name, String? phone, String? username, String? locale
});




}
/// @nodoc
class _$AccountUserCopyWithImpl<$Res>
    implements $AccountUserCopyWith<$Res> {
  _$AccountUserCopyWithImpl(this._self, this._then);

  final AccountUser _self;
  final $Res Function(AccountUser) _then;

/// Create a copy of AccountUser
/// with the given fields replaced by the non-null parameter values.
@pragma('vm:prefer-inline') @override $Res call({Object? id = null,Object? name = null,Object? phone = freezed,Object? username = freezed,Object? locale = freezed,}) {
  return _then(_self.copyWith(
id: null == id ? _self.id : id // ignore: cast_nullable_to_non_nullable
as int,name: null == name ? _self.name : name // ignore: cast_nullable_to_non_nullable
as String,phone: freezed == phone ? _self.phone : phone // ignore: cast_nullable_to_non_nullable
as String?,username: freezed == username ? _self.username : username // ignore: cast_nullable_to_non_nullable
as String?,locale: freezed == locale ? _self.locale : locale // ignore: cast_nullable_to_non_nullable
as String?,
  ));
}

}


/// Adds pattern-matching-related methods to [AccountUser].
extension AccountUserPatterns on AccountUser {
/// A variant of `map` that fallback to returning `orElse`.
///
/// It is equivalent to doing:
/// ```dart
/// switch (sealedClass) {
///   case final Subclass value:
///     return ...;
///   case _:
///     return orElse();
/// }
/// ```

@optionalTypeArgs TResult maybeMap<TResult extends Object?>(TResult Function( _AccountUser value)?  $default,{required TResult orElse(),}){
final _that = this;
switch (_that) {
case _AccountUser() when $default != null:
return $default(_that);case _:
  return orElse();

}
}
/// A `switch`-like method, using callbacks.
///
/// Callbacks receives the raw object, upcasted.
/// It is equivalent to doing:
/// ```dart
/// switch (sealedClass) {
///   case final Subclass value:
///     return ...;
///   case final Subclass2 value:
///     return ...;
/// }
/// ```

@optionalTypeArgs TResult map<TResult extends Object?>(TResult Function( _AccountUser value)  $default,){
final _that = this;
switch (_that) {
case _AccountUser():
return $default(_that);case _:
  throw StateError('Unexpected subclass');

}
}
/// A variant of `map` that fallback to returning `null`.
///
/// It is equivalent to doing:
/// ```dart
/// switch (sealedClass) {
///   case final Subclass value:
///     return ...;
///   case _:
///     return null;
/// }
/// ```

@optionalTypeArgs TResult? mapOrNull<TResult extends Object?>(TResult? Function( _AccountUser value)?  $default,){
final _that = this;
switch (_that) {
case _AccountUser() when $default != null:
return $default(_that);case _:
  return null;

}
}
/// A variant of `when` that fallback to an `orElse` callback.
///
/// It is equivalent to doing:
/// ```dart
/// switch (sealedClass) {
///   case Subclass(:final field):
///     return ...;
///   case _:
///     return orElse();
/// }
/// ```

@optionalTypeArgs TResult maybeWhen<TResult extends Object?>(TResult Function( int id,  String name,  String? phone,  String? username,  String? locale)?  $default,{required TResult orElse(),}) {final _that = this;
switch (_that) {
case _AccountUser() when $default != null:
return $default(_that.id,_that.name,_that.phone,_that.username,_that.locale);case _:
  return orElse();

}
}
/// A `switch`-like method, using callbacks.
///
/// As opposed to `map`, this offers destructuring.
/// It is equivalent to doing:
/// ```dart
/// switch (sealedClass) {
///   case Subclass(:final field):
///     return ...;
///   case Subclass2(:final field2):
///     return ...;
/// }
/// ```

@optionalTypeArgs TResult when<TResult extends Object?>(TResult Function( int id,  String name,  String? phone,  String? username,  String? locale)  $default,) {final _that = this;
switch (_that) {
case _AccountUser():
return $default(_that.id,_that.name,_that.phone,_that.username,_that.locale);case _:
  throw StateError('Unexpected subclass');

}
}
/// A variant of `when` that fallback to returning `null`
///
/// It is equivalent to doing:
/// ```dart
/// switch (sealedClass) {
///   case Subclass(:final field):
///     return ...;
///   case _:
///     return null;
/// }
/// ```

@optionalTypeArgs TResult? whenOrNull<TResult extends Object?>(TResult? Function( int id,  String name,  String? phone,  String? username,  String? locale)?  $default,) {final _that = this;
switch (_that) {
case _AccountUser() when $default != null:
return $default(_that.id,_that.name,_that.phone,_that.username,_that.locale);case _:
  return null;

}
}

}

/// @nodoc
@JsonSerializable()

class _AccountUser implements AccountUser {
  const _AccountUser({required this.id, required this.name, this.phone, this.username, this.locale});
  factory _AccountUser.fromJson(Map<String, dynamic> json) => _$AccountUserFromJson(json);

@override final  int id;
@override final  String name;
@override final  String? phone;
@override final  String? username;
@override final  String? locale;

/// Create a copy of AccountUser
/// with the given fields replaced by the non-null parameter values.
@override @JsonKey(includeFromJson: false, includeToJson: false)
@pragma('vm:prefer-inline')
_$AccountUserCopyWith<_AccountUser> get copyWith => __$AccountUserCopyWithImpl<_AccountUser>(this, _$identity);

@override
Map<String, dynamic> toJson() {
  return _$AccountUserToJson(this, );
}

@override
bool operator ==(Object other) {
  return identical(this, other) || (other.runtimeType == runtimeType&&other is _AccountUser&&(identical(other.id, id) || other.id == id)&&(identical(other.name, name) || other.name == name)&&(identical(other.phone, phone) || other.phone == phone)&&(identical(other.username, username) || other.username == username)&&(identical(other.locale, locale) || other.locale == locale));
}

@JsonKey(includeFromJson: false, includeToJson: false)
@override
int get hashCode => Object.hash(runtimeType,id,name,phone,username,locale);

@override
String toString() {
  return 'AccountUser(id: $id, name: $name, phone: $phone, username: $username, locale: $locale)';
}


}

/// @nodoc
abstract mixin class _$AccountUserCopyWith<$Res> implements $AccountUserCopyWith<$Res> {
  factory _$AccountUserCopyWith(_AccountUser value, $Res Function(_AccountUser) _then) = __$AccountUserCopyWithImpl;
@override @useResult
$Res call({
 int id, String name, String? phone, String? username, String? locale
});




}
/// @nodoc
class __$AccountUserCopyWithImpl<$Res>
    implements _$AccountUserCopyWith<$Res> {
  __$AccountUserCopyWithImpl(this._self, this._then);

  final _AccountUser _self;
  final $Res Function(_AccountUser) _then;

/// Create a copy of AccountUser
/// with the given fields replaced by the non-null parameter values.
@override @pragma('vm:prefer-inline') $Res call({Object? id = null,Object? name = null,Object? phone = freezed,Object? username = freezed,Object? locale = freezed,}) {
  return _then(_AccountUser(
id: null == id ? _self.id : id // ignore: cast_nullable_to_non_nullable
as int,name: null == name ? _self.name : name // ignore: cast_nullable_to_non_nullable
as String,phone: freezed == phone ? _self.phone : phone // ignore: cast_nullable_to_non_nullable
as String?,username: freezed == username ? _self.username : username // ignore: cast_nullable_to_non_nullable
as String?,locale: freezed == locale ? _self.locale : locale // ignore: cast_nullable_to_non_nullable
as String?,
  ));
}


}


/// @nodoc
mixin _$AuthSession {

 String get token; AccountUser get user;@JsonKey(name: 'is_new_account') bool get isNewAccount;
/// Create a copy of AuthSession
/// with the given fields replaced by the non-null parameter values.
@JsonKey(includeFromJson: false, includeToJson: false)
@pragma('vm:prefer-inline')
$AuthSessionCopyWith<AuthSession> get copyWith => _$AuthSessionCopyWithImpl<AuthSession>(this as AuthSession, _$identity);

  /// Serializes this AuthSession to a JSON map.
  Map<String, dynamic> toJson();


@override
bool operator ==(Object other) {
  return identical(this, other) || (other.runtimeType == runtimeType&&other is AuthSession&&(identical(other.token, token) || other.token == token)&&(identical(other.user, user) || other.user == user)&&(identical(other.isNewAccount, isNewAccount) || other.isNewAccount == isNewAccount));
}

@JsonKey(includeFromJson: false, includeToJson: false)
@override
int get hashCode => Object.hash(runtimeType,token,user,isNewAccount);

@override
String toString() {
  return 'AuthSession(token: $token, user: $user, isNewAccount: $isNewAccount)';
}


}

/// @nodoc
abstract mixin class $AuthSessionCopyWith<$Res>  {
  factory $AuthSessionCopyWith(AuthSession value, $Res Function(AuthSession) _then) = _$AuthSessionCopyWithImpl;
@useResult
$Res call({
 String token, AccountUser user,@JsonKey(name: 'is_new_account') bool isNewAccount
});


$AccountUserCopyWith<$Res> get user;

}
/// @nodoc
class _$AuthSessionCopyWithImpl<$Res>
    implements $AuthSessionCopyWith<$Res> {
  _$AuthSessionCopyWithImpl(this._self, this._then);

  final AuthSession _self;
  final $Res Function(AuthSession) _then;

/// Create a copy of AuthSession
/// with the given fields replaced by the non-null parameter values.
@pragma('vm:prefer-inline') @override $Res call({Object? token = null,Object? user = null,Object? isNewAccount = null,}) {
  return _then(_self.copyWith(
token: null == token ? _self.token : token // ignore: cast_nullable_to_non_nullable
as String,user: null == user ? _self.user : user // ignore: cast_nullable_to_non_nullable
as AccountUser,isNewAccount: null == isNewAccount ? _self.isNewAccount : isNewAccount // ignore: cast_nullable_to_non_nullable
as bool,
  ));
}
/// Create a copy of AuthSession
/// with the given fields replaced by the non-null parameter values.
@override
@pragma('vm:prefer-inline')
$AccountUserCopyWith<$Res> get user {
  
  return $AccountUserCopyWith<$Res>(_self.user, (value) {
    return _then(_self.copyWith(user: value));
  });
}
}


/// Adds pattern-matching-related methods to [AuthSession].
extension AuthSessionPatterns on AuthSession {
/// A variant of `map` that fallback to returning `orElse`.
///
/// It is equivalent to doing:
/// ```dart
/// switch (sealedClass) {
///   case final Subclass value:
///     return ...;
///   case _:
///     return orElse();
/// }
/// ```

@optionalTypeArgs TResult maybeMap<TResult extends Object?>(TResult Function( _AuthSession value)?  $default,{required TResult orElse(),}){
final _that = this;
switch (_that) {
case _AuthSession() when $default != null:
return $default(_that);case _:
  return orElse();

}
}
/// A `switch`-like method, using callbacks.
///
/// Callbacks receives the raw object, upcasted.
/// It is equivalent to doing:
/// ```dart
/// switch (sealedClass) {
///   case final Subclass value:
///     return ...;
///   case final Subclass2 value:
///     return ...;
/// }
/// ```

@optionalTypeArgs TResult map<TResult extends Object?>(TResult Function( _AuthSession value)  $default,){
final _that = this;
switch (_that) {
case _AuthSession():
return $default(_that);case _:
  throw StateError('Unexpected subclass');

}
}
/// A variant of `map` that fallback to returning `null`.
///
/// It is equivalent to doing:
/// ```dart
/// switch (sealedClass) {
///   case final Subclass value:
///     return ...;
///   case _:
///     return null;
/// }
/// ```

@optionalTypeArgs TResult? mapOrNull<TResult extends Object?>(TResult? Function( _AuthSession value)?  $default,){
final _that = this;
switch (_that) {
case _AuthSession() when $default != null:
return $default(_that);case _:
  return null;

}
}
/// A variant of `when` that fallback to an `orElse` callback.
///
/// It is equivalent to doing:
/// ```dart
/// switch (sealedClass) {
///   case Subclass(:final field):
///     return ...;
///   case _:
///     return orElse();
/// }
/// ```

@optionalTypeArgs TResult maybeWhen<TResult extends Object?>(TResult Function( String token,  AccountUser user, @JsonKey(name: 'is_new_account')  bool isNewAccount)?  $default,{required TResult orElse(),}) {final _that = this;
switch (_that) {
case _AuthSession() when $default != null:
return $default(_that.token,_that.user,_that.isNewAccount);case _:
  return orElse();

}
}
/// A `switch`-like method, using callbacks.
///
/// As opposed to `map`, this offers destructuring.
/// It is equivalent to doing:
/// ```dart
/// switch (sealedClass) {
///   case Subclass(:final field):
///     return ...;
///   case Subclass2(:final field2):
///     return ...;
/// }
/// ```

@optionalTypeArgs TResult when<TResult extends Object?>(TResult Function( String token,  AccountUser user, @JsonKey(name: 'is_new_account')  bool isNewAccount)  $default,) {final _that = this;
switch (_that) {
case _AuthSession():
return $default(_that.token,_that.user,_that.isNewAccount);case _:
  throw StateError('Unexpected subclass');

}
}
/// A variant of `when` that fallback to returning `null`
///
/// It is equivalent to doing:
/// ```dart
/// switch (sealedClass) {
///   case Subclass(:final field):
///     return ...;
///   case _:
///     return null;
/// }
/// ```

@optionalTypeArgs TResult? whenOrNull<TResult extends Object?>(TResult? Function( String token,  AccountUser user, @JsonKey(name: 'is_new_account')  bool isNewAccount)?  $default,) {final _that = this;
switch (_that) {
case _AuthSession() when $default != null:
return $default(_that.token,_that.user,_that.isNewAccount);case _:
  return null;

}
}

}

/// @nodoc
@JsonSerializable()

class _AuthSession implements AuthSession {
  const _AuthSession({required this.token, required this.user, @JsonKey(name: 'is_new_account') this.isNewAccount = false});
  factory _AuthSession.fromJson(Map<String, dynamic> json) => _$AuthSessionFromJson(json);

@override final  String token;
@override final  AccountUser user;
@override@JsonKey(name: 'is_new_account') final  bool isNewAccount;

/// Create a copy of AuthSession
/// with the given fields replaced by the non-null parameter values.
@override @JsonKey(includeFromJson: false, includeToJson: false)
@pragma('vm:prefer-inline')
_$AuthSessionCopyWith<_AuthSession> get copyWith => __$AuthSessionCopyWithImpl<_AuthSession>(this, _$identity);

@override
Map<String, dynamic> toJson() {
  return _$AuthSessionToJson(this, );
}

@override
bool operator ==(Object other) {
  return identical(this, other) || (other.runtimeType == runtimeType&&other is _AuthSession&&(identical(other.token, token) || other.token == token)&&(identical(other.user, user) || other.user == user)&&(identical(other.isNewAccount, isNewAccount) || other.isNewAccount == isNewAccount));
}

@JsonKey(includeFromJson: false, includeToJson: false)
@override
int get hashCode => Object.hash(runtimeType,token,user,isNewAccount);

@override
String toString() {
  return 'AuthSession(token: $token, user: $user, isNewAccount: $isNewAccount)';
}


}

/// @nodoc
abstract mixin class _$AuthSessionCopyWith<$Res> implements $AuthSessionCopyWith<$Res> {
  factory _$AuthSessionCopyWith(_AuthSession value, $Res Function(_AuthSession) _then) = __$AuthSessionCopyWithImpl;
@override @useResult
$Res call({
 String token, AccountUser user,@JsonKey(name: 'is_new_account') bool isNewAccount
});


@override $AccountUserCopyWith<$Res> get user;

}
/// @nodoc
class __$AuthSessionCopyWithImpl<$Res>
    implements _$AuthSessionCopyWith<$Res> {
  __$AuthSessionCopyWithImpl(this._self, this._then);

  final _AuthSession _self;
  final $Res Function(_AuthSession) _then;

/// Create a copy of AuthSession
/// with the given fields replaced by the non-null parameter values.
@override @pragma('vm:prefer-inline') $Res call({Object? token = null,Object? user = null,Object? isNewAccount = null,}) {
  return _then(_AuthSession(
token: null == token ? _self.token : token // ignore: cast_nullable_to_non_nullable
as String,user: null == user ? _self.user : user // ignore: cast_nullable_to_non_nullable
as AccountUser,isNewAccount: null == isNewAccount ? _self.isNewAccount : isNewAccount // ignore: cast_nullable_to_non_nullable
as bool,
  ));
}

/// Create a copy of AuthSession
/// with the given fields replaced by the non-null parameter values.
@override
@pragma('vm:prefer-inline')
$AccountUserCopyWith<$Res> get user {
  
  return $AccountUserCopyWith<$Res>(_self.user, (value) {
    return _then(_self.copyWith(user: value));
  });
}
}

// dart format on
