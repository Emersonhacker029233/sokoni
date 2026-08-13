// GENERATED CODE - DO NOT MODIFY BY HAND
// coverage:ignore-file
// ignore_for_file: type=lint
// ignore_for_file: unused_element, deprecated_member_use, deprecated_member_use_from_same_package, use_function_type_syntax_for_parameters, unnecessary_const, avoid_init_to_null, invalid_override_different_default_values_named, prefer_expression_function_bodies, annotate_overrides, invalid_annotation_target, unnecessary_question_mark

part of 'user.dart';

// **************************************************************************
// FreezedGenerator
// **************************************************************************

// dart format off
T _$identity<T>(T value) => value;

/// @nodoc
mixin _$SokoniUser {

 int get id; String get name; String? get email; String? get phone; String? get avatar; String? get locale;@JsonKey(name: 'is_seller') bool get isSeller;@JsonKey(name: 'seller_status') String? get sellerStatus;@JsonKey(name: 'terms_accepted') bool get termsAccepted;@JsonKey(name: 'created_at') DateTime? get createdAt;
/// Create a copy of SokoniUser
/// with the given fields replaced by the non-null parameter values.
@JsonKey(includeFromJson: false, includeToJson: false)
@pragma('vm:prefer-inline')
$SokoniUserCopyWith<SokoniUser> get copyWith => _$SokoniUserCopyWithImpl<SokoniUser>(this as SokoniUser, _$identity);

  /// Serializes this SokoniUser to a JSON map.
  Map<String, dynamic> toJson();


@override
bool operator ==(Object other) {
  return identical(this, other) || (other.runtimeType == runtimeType&&other is SokoniUser&&(identical(other.id, id) || other.id == id)&&(identical(other.name, name) || other.name == name)&&(identical(other.email, email) || other.email == email)&&(identical(other.phone, phone) || other.phone == phone)&&(identical(other.avatar, avatar) || other.avatar == avatar)&&(identical(other.locale, locale) || other.locale == locale)&&(identical(other.isSeller, isSeller) || other.isSeller == isSeller)&&(identical(other.sellerStatus, sellerStatus) || other.sellerStatus == sellerStatus)&&(identical(other.termsAccepted, termsAccepted) || other.termsAccepted == termsAccepted)&&(identical(other.createdAt, createdAt) || other.createdAt == createdAt));
}

@JsonKey(includeFromJson: false, includeToJson: false)
@override
int get hashCode => Object.hash(runtimeType,id,name,email,phone,avatar,locale,isSeller,sellerStatus,termsAccepted,createdAt);

@override
String toString() {
  return 'SokoniUser(id: $id, name: $name, email: $email, phone: $phone, avatar: $avatar, locale: $locale, isSeller: $isSeller, sellerStatus: $sellerStatus, termsAccepted: $termsAccepted, createdAt: $createdAt)';
}


}

/// @nodoc
abstract mixin class $SokoniUserCopyWith<$Res>  {
  factory $SokoniUserCopyWith(SokoniUser value, $Res Function(SokoniUser) _then) = _$SokoniUserCopyWithImpl;
@useResult
$Res call({
 int id, String name, String? email, String? phone, String? avatar, String? locale,@JsonKey(name: 'is_seller') bool isSeller,@JsonKey(name: 'seller_status') String? sellerStatus,@JsonKey(name: 'terms_accepted') bool termsAccepted,@JsonKey(name: 'created_at') DateTime? createdAt
});




}
/// @nodoc
class _$SokoniUserCopyWithImpl<$Res>
    implements $SokoniUserCopyWith<$Res> {
  _$SokoniUserCopyWithImpl(this._self, this._then);

  final SokoniUser _self;
  final $Res Function(SokoniUser) _then;

/// Create a copy of SokoniUser
/// with the given fields replaced by the non-null parameter values.
@pragma('vm:prefer-inline') @override $Res call({Object? id = null,Object? name = null,Object? email = freezed,Object? phone = freezed,Object? avatar = freezed,Object? locale = freezed,Object? isSeller = null,Object? sellerStatus = freezed,Object? termsAccepted = null,Object? createdAt = freezed,}) {
  return _then(_self.copyWith(
id: null == id ? _self.id : id // ignore: cast_nullable_to_non_nullable
as int,name: null == name ? _self.name : name // ignore: cast_nullable_to_non_nullable
as String,email: freezed == email ? _self.email : email // ignore: cast_nullable_to_non_nullable
as String?,phone: freezed == phone ? _self.phone : phone // ignore: cast_nullable_to_non_nullable
as String?,avatar: freezed == avatar ? _self.avatar : avatar // ignore: cast_nullable_to_non_nullable
as String?,locale: freezed == locale ? _self.locale : locale // ignore: cast_nullable_to_non_nullable
as String?,isSeller: null == isSeller ? _self.isSeller : isSeller // ignore: cast_nullable_to_non_nullable
as bool,sellerStatus: freezed == sellerStatus ? _self.sellerStatus : sellerStatus // ignore: cast_nullable_to_non_nullable
as String?,termsAccepted: null == termsAccepted ? _self.termsAccepted : termsAccepted // ignore: cast_nullable_to_non_nullable
as bool,createdAt: freezed == createdAt ? _self.createdAt : createdAt // ignore: cast_nullable_to_non_nullable
as DateTime?,
  ));
}

}


/// Adds pattern-matching-related methods to [SokoniUser].
extension SokoniUserPatterns on SokoniUser {
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

@optionalTypeArgs TResult maybeMap<TResult extends Object?>(TResult Function( _SokoniUser value)?  $default,{required TResult orElse(),}){
final _that = this;
switch (_that) {
case _SokoniUser() when $default != null:
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

@optionalTypeArgs TResult map<TResult extends Object?>(TResult Function( _SokoniUser value)  $default,){
final _that = this;
switch (_that) {
case _SokoniUser():
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

@optionalTypeArgs TResult? mapOrNull<TResult extends Object?>(TResult? Function( _SokoniUser value)?  $default,){
final _that = this;
switch (_that) {
case _SokoniUser() when $default != null:
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

@optionalTypeArgs TResult maybeWhen<TResult extends Object?>(TResult Function( int id,  String name,  String? email,  String? phone,  String? avatar,  String? locale, @JsonKey(name: 'is_seller')  bool isSeller, @JsonKey(name: 'seller_status')  String? sellerStatus, @JsonKey(name: 'terms_accepted')  bool termsAccepted, @JsonKey(name: 'created_at')  DateTime? createdAt)?  $default,{required TResult orElse(),}) {final _that = this;
switch (_that) {
case _SokoniUser() when $default != null:
return $default(_that.id,_that.name,_that.email,_that.phone,_that.avatar,_that.locale,_that.isSeller,_that.sellerStatus,_that.termsAccepted,_that.createdAt);case _:
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

@optionalTypeArgs TResult when<TResult extends Object?>(TResult Function( int id,  String name,  String? email,  String? phone,  String? avatar,  String? locale, @JsonKey(name: 'is_seller')  bool isSeller, @JsonKey(name: 'seller_status')  String? sellerStatus, @JsonKey(name: 'terms_accepted')  bool termsAccepted, @JsonKey(name: 'created_at')  DateTime? createdAt)  $default,) {final _that = this;
switch (_that) {
case _SokoniUser():
return $default(_that.id,_that.name,_that.email,_that.phone,_that.avatar,_that.locale,_that.isSeller,_that.sellerStatus,_that.termsAccepted,_that.createdAt);case _:
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

@optionalTypeArgs TResult? whenOrNull<TResult extends Object?>(TResult? Function( int id,  String name,  String? email,  String? phone,  String? avatar,  String? locale, @JsonKey(name: 'is_seller')  bool isSeller, @JsonKey(name: 'seller_status')  String? sellerStatus, @JsonKey(name: 'terms_accepted')  bool termsAccepted, @JsonKey(name: 'created_at')  DateTime? createdAt)?  $default,) {final _that = this;
switch (_that) {
case _SokoniUser() when $default != null:
return $default(_that.id,_that.name,_that.email,_that.phone,_that.avatar,_that.locale,_that.isSeller,_that.sellerStatus,_that.termsAccepted,_that.createdAt);case _:
  return null;

}
}

}

/// @nodoc
@JsonSerializable()

class _SokoniUser implements SokoniUser {
  const _SokoniUser({required this.id, required this.name, this.email, this.phone, this.avatar, this.locale, @JsonKey(name: 'is_seller') required this.isSeller, @JsonKey(name: 'seller_status') this.sellerStatus, @JsonKey(name: 'terms_accepted') required this.termsAccepted, @JsonKey(name: 'created_at') this.createdAt});
  factory _SokoniUser.fromJson(Map<String, dynamic> json) => _$SokoniUserFromJson(json);

@override final  int id;
@override final  String name;
@override final  String? email;
@override final  String? phone;
@override final  String? avatar;
@override final  String? locale;
@override@JsonKey(name: 'is_seller') final  bool isSeller;
@override@JsonKey(name: 'seller_status') final  String? sellerStatus;
@override@JsonKey(name: 'terms_accepted') final  bool termsAccepted;
@override@JsonKey(name: 'created_at') final  DateTime? createdAt;

/// Create a copy of SokoniUser
/// with the given fields replaced by the non-null parameter values.
@override @JsonKey(includeFromJson: false, includeToJson: false)
@pragma('vm:prefer-inline')
_$SokoniUserCopyWith<_SokoniUser> get copyWith => __$SokoniUserCopyWithImpl<_SokoniUser>(this, _$identity);

@override
Map<String, dynamic> toJson() {
  return _$SokoniUserToJson(this, );
}

@override
bool operator ==(Object other) {
  return identical(this, other) || (other.runtimeType == runtimeType&&other is _SokoniUser&&(identical(other.id, id) || other.id == id)&&(identical(other.name, name) || other.name == name)&&(identical(other.email, email) || other.email == email)&&(identical(other.phone, phone) || other.phone == phone)&&(identical(other.avatar, avatar) || other.avatar == avatar)&&(identical(other.locale, locale) || other.locale == locale)&&(identical(other.isSeller, isSeller) || other.isSeller == isSeller)&&(identical(other.sellerStatus, sellerStatus) || other.sellerStatus == sellerStatus)&&(identical(other.termsAccepted, termsAccepted) || other.termsAccepted == termsAccepted)&&(identical(other.createdAt, createdAt) || other.createdAt == createdAt));
}

@JsonKey(includeFromJson: false, includeToJson: false)
@override
int get hashCode => Object.hash(runtimeType,id,name,email,phone,avatar,locale,isSeller,sellerStatus,termsAccepted,createdAt);

@override
String toString() {
  return 'SokoniUser(id: $id, name: $name, email: $email, phone: $phone, avatar: $avatar, locale: $locale, isSeller: $isSeller, sellerStatus: $sellerStatus, termsAccepted: $termsAccepted, createdAt: $createdAt)';
}


}

/// @nodoc
abstract mixin class _$SokoniUserCopyWith<$Res> implements $SokoniUserCopyWith<$Res> {
  factory _$SokoniUserCopyWith(_SokoniUser value, $Res Function(_SokoniUser) _then) = __$SokoniUserCopyWithImpl;
@override @useResult
$Res call({
 int id, String name, String? email, String? phone, String? avatar, String? locale,@JsonKey(name: 'is_seller') bool isSeller,@JsonKey(name: 'seller_status') String? sellerStatus,@JsonKey(name: 'terms_accepted') bool termsAccepted,@JsonKey(name: 'created_at') DateTime? createdAt
});




}
/// @nodoc
class __$SokoniUserCopyWithImpl<$Res>
    implements _$SokoniUserCopyWith<$Res> {
  __$SokoniUserCopyWithImpl(this._self, this._then);

  final _SokoniUser _self;
  final $Res Function(_SokoniUser) _then;

/// Create a copy of SokoniUser
/// with the given fields replaced by the non-null parameter values.
@override @pragma('vm:prefer-inline') $Res call({Object? id = null,Object? name = null,Object? email = freezed,Object? phone = freezed,Object? avatar = freezed,Object? locale = freezed,Object? isSeller = null,Object? sellerStatus = freezed,Object? termsAccepted = null,Object? createdAt = freezed,}) {
  return _then(_SokoniUser(
id: null == id ? _self.id : id // ignore: cast_nullable_to_non_nullable
as int,name: null == name ? _self.name : name // ignore: cast_nullable_to_non_nullable
as String,email: freezed == email ? _self.email : email // ignore: cast_nullable_to_non_nullable
as String?,phone: freezed == phone ? _self.phone : phone // ignore: cast_nullable_to_non_nullable
as String?,avatar: freezed == avatar ? _self.avatar : avatar // ignore: cast_nullable_to_non_nullable
as String?,locale: freezed == locale ? _self.locale : locale // ignore: cast_nullable_to_non_nullable
as String?,isSeller: null == isSeller ? _self.isSeller : isSeller // ignore: cast_nullable_to_non_nullable
as bool,sellerStatus: freezed == sellerStatus ? _self.sellerStatus : sellerStatus // ignore: cast_nullable_to_non_nullable
as String?,termsAccepted: null == termsAccepted ? _self.termsAccepted : termsAccepted // ignore: cast_nullable_to_non_nullable
as bool,createdAt: freezed == createdAt ? _self.createdAt : createdAt // ignore: cast_nullable_to_non_nullable
as DateTime?,
  ));
}


}

// dart format on
