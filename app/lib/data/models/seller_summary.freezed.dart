// GENERATED CODE - DO NOT MODIFY BY HAND
// coverage:ignore-file
// ignore_for_file: type=lint
// ignore_for_file: unused_element, deprecated_member_use, deprecated_member_use_from_same_package, use_function_type_syntax_for_parameters, unnecessary_const, avoid_init_to_null, invalid_override_different_default_values_named, prefer_expression_function_bodies, annotate_overrides, invalid_annotation_target, unnecessary_question_mark

part of 'seller_summary.dart';

// **************************************************************************
// FreezedGenerator
// **************************************************************************

// dart format off
T _$identity<T>(T value) => value;

/// @nodoc
mixin _$SellerSummary {

 int get id;@JsonKey(name: 'shop_name') String get shopName; String get handle;@JsonKey(name: 'is_verified') bool get isVerified;@JsonKey(name: 'rating_avg') double get ratingAvg;@JsonKey(name: 'rating_count') int get ratingCount; double? get lat; double? get lng; String? get whatsapp;
/// Create a copy of SellerSummary
/// with the given fields replaced by the non-null parameter values.
@JsonKey(includeFromJson: false, includeToJson: false)
@pragma('vm:prefer-inline')
$SellerSummaryCopyWith<SellerSummary> get copyWith => _$SellerSummaryCopyWithImpl<SellerSummary>(this as SellerSummary, _$identity);

  /// Serializes this SellerSummary to a JSON map.
  Map<String, dynamic> toJson();


@override
bool operator ==(Object other) {
  return identical(this, other) || (other.runtimeType == runtimeType&&other is SellerSummary&&(identical(other.id, id) || other.id == id)&&(identical(other.shopName, shopName) || other.shopName == shopName)&&(identical(other.handle, handle) || other.handle == handle)&&(identical(other.isVerified, isVerified) || other.isVerified == isVerified)&&(identical(other.ratingAvg, ratingAvg) || other.ratingAvg == ratingAvg)&&(identical(other.ratingCount, ratingCount) || other.ratingCount == ratingCount)&&(identical(other.lat, lat) || other.lat == lat)&&(identical(other.lng, lng) || other.lng == lng)&&(identical(other.whatsapp, whatsapp) || other.whatsapp == whatsapp));
}

@JsonKey(includeFromJson: false, includeToJson: false)
@override
int get hashCode => Object.hash(runtimeType,id,shopName,handle,isVerified,ratingAvg,ratingCount,lat,lng,whatsapp);

@override
String toString() {
  return 'SellerSummary(id: $id, shopName: $shopName, handle: $handle, isVerified: $isVerified, ratingAvg: $ratingAvg, ratingCount: $ratingCount, lat: $lat, lng: $lng, whatsapp: $whatsapp)';
}


}

/// @nodoc
abstract mixin class $SellerSummaryCopyWith<$Res>  {
  factory $SellerSummaryCopyWith(SellerSummary value, $Res Function(SellerSummary) _then) = _$SellerSummaryCopyWithImpl;
@useResult
$Res call({
 int id,@JsonKey(name: 'shop_name') String shopName, String handle,@JsonKey(name: 'is_verified') bool isVerified,@JsonKey(name: 'rating_avg') double ratingAvg,@JsonKey(name: 'rating_count') int ratingCount, double? lat, double? lng, String? whatsapp
});




}
/// @nodoc
class _$SellerSummaryCopyWithImpl<$Res>
    implements $SellerSummaryCopyWith<$Res> {
  _$SellerSummaryCopyWithImpl(this._self, this._then);

  final SellerSummary _self;
  final $Res Function(SellerSummary) _then;

/// Create a copy of SellerSummary
/// with the given fields replaced by the non-null parameter values.
@pragma('vm:prefer-inline') @override $Res call({Object? id = null,Object? shopName = null,Object? handle = null,Object? isVerified = null,Object? ratingAvg = null,Object? ratingCount = null,Object? lat = freezed,Object? lng = freezed,Object? whatsapp = freezed,}) {
  return _then(_self.copyWith(
id: null == id ? _self.id : id // ignore: cast_nullable_to_non_nullable
as int,shopName: null == shopName ? _self.shopName : shopName // ignore: cast_nullable_to_non_nullable
as String,handle: null == handle ? _self.handle : handle // ignore: cast_nullable_to_non_nullable
as String,isVerified: null == isVerified ? _self.isVerified : isVerified // ignore: cast_nullable_to_non_nullable
as bool,ratingAvg: null == ratingAvg ? _self.ratingAvg : ratingAvg // ignore: cast_nullable_to_non_nullable
as double,ratingCount: null == ratingCount ? _self.ratingCount : ratingCount // ignore: cast_nullable_to_non_nullable
as int,lat: freezed == lat ? _self.lat : lat // ignore: cast_nullable_to_non_nullable
as double?,lng: freezed == lng ? _self.lng : lng // ignore: cast_nullable_to_non_nullable
as double?,whatsapp: freezed == whatsapp ? _self.whatsapp : whatsapp // ignore: cast_nullable_to_non_nullable
as String?,
  ));
}

}


/// Adds pattern-matching-related methods to [SellerSummary].
extension SellerSummaryPatterns on SellerSummary {
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

@optionalTypeArgs TResult maybeMap<TResult extends Object?>(TResult Function( _SellerSummary value)?  $default,{required TResult orElse(),}){
final _that = this;
switch (_that) {
case _SellerSummary() when $default != null:
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

@optionalTypeArgs TResult map<TResult extends Object?>(TResult Function( _SellerSummary value)  $default,){
final _that = this;
switch (_that) {
case _SellerSummary():
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

@optionalTypeArgs TResult? mapOrNull<TResult extends Object?>(TResult? Function( _SellerSummary value)?  $default,){
final _that = this;
switch (_that) {
case _SellerSummary() when $default != null:
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

@optionalTypeArgs TResult maybeWhen<TResult extends Object?>(TResult Function( int id, @JsonKey(name: 'shop_name')  String shopName,  String handle, @JsonKey(name: 'is_verified')  bool isVerified, @JsonKey(name: 'rating_avg')  double ratingAvg, @JsonKey(name: 'rating_count')  int ratingCount,  double? lat,  double? lng,  String? whatsapp)?  $default,{required TResult orElse(),}) {final _that = this;
switch (_that) {
case _SellerSummary() when $default != null:
return $default(_that.id,_that.shopName,_that.handle,_that.isVerified,_that.ratingAvg,_that.ratingCount,_that.lat,_that.lng,_that.whatsapp);case _:
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

@optionalTypeArgs TResult when<TResult extends Object?>(TResult Function( int id, @JsonKey(name: 'shop_name')  String shopName,  String handle, @JsonKey(name: 'is_verified')  bool isVerified, @JsonKey(name: 'rating_avg')  double ratingAvg, @JsonKey(name: 'rating_count')  int ratingCount,  double? lat,  double? lng,  String? whatsapp)  $default,) {final _that = this;
switch (_that) {
case _SellerSummary():
return $default(_that.id,_that.shopName,_that.handle,_that.isVerified,_that.ratingAvg,_that.ratingCount,_that.lat,_that.lng,_that.whatsapp);case _:
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

@optionalTypeArgs TResult? whenOrNull<TResult extends Object?>(TResult? Function( int id, @JsonKey(name: 'shop_name')  String shopName,  String handle, @JsonKey(name: 'is_verified')  bool isVerified, @JsonKey(name: 'rating_avg')  double ratingAvg, @JsonKey(name: 'rating_count')  int ratingCount,  double? lat,  double? lng,  String? whatsapp)?  $default,) {final _that = this;
switch (_that) {
case _SellerSummary() when $default != null:
return $default(_that.id,_that.shopName,_that.handle,_that.isVerified,_that.ratingAvg,_that.ratingCount,_that.lat,_that.lng,_that.whatsapp);case _:
  return null;

}
}

}

/// @nodoc
@JsonSerializable()

class _SellerSummary implements SellerSummary {
  const _SellerSummary({required this.id, @JsonKey(name: 'shop_name') required this.shopName, required this.handle, @JsonKey(name: 'is_verified') this.isVerified = false, @JsonKey(name: 'rating_avg') this.ratingAvg = 0, @JsonKey(name: 'rating_count') this.ratingCount = 0, this.lat, this.lng, this.whatsapp});
  factory _SellerSummary.fromJson(Map<String, dynamic> json) => _$SellerSummaryFromJson(json);

@override final  int id;
@override@JsonKey(name: 'shop_name') final  String shopName;
@override final  String handle;
@override@JsonKey(name: 'is_verified') final  bool isVerified;
@override@JsonKey(name: 'rating_avg') final  double ratingAvg;
@override@JsonKey(name: 'rating_count') final  int ratingCount;
@override final  double? lat;
@override final  double? lng;
@override final  String? whatsapp;

/// Create a copy of SellerSummary
/// with the given fields replaced by the non-null parameter values.
@override @JsonKey(includeFromJson: false, includeToJson: false)
@pragma('vm:prefer-inline')
_$SellerSummaryCopyWith<_SellerSummary> get copyWith => __$SellerSummaryCopyWithImpl<_SellerSummary>(this, _$identity);

@override
Map<String, dynamic> toJson() {
  return _$SellerSummaryToJson(this, );
}

@override
bool operator ==(Object other) {
  return identical(this, other) || (other.runtimeType == runtimeType&&other is _SellerSummary&&(identical(other.id, id) || other.id == id)&&(identical(other.shopName, shopName) || other.shopName == shopName)&&(identical(other.handle, handle) || other.handle == handle)&&(identical(other.isVerified, isVerified) || other.isVerified == isVerified)&&(identical(other.ratingAvg, ratingAvg) || other.ratingAvg == ratingAvg)&&(identical(other.ratingCount, ratingCount) || other.ratingCount == ratingCount)&&(identical(other.lat, lat) || other.lat == lat)&&(identical(other.lng, lng) || other.lng == lng)&&(identical(other.whatsapp, whatsapp) || other.whatsapp == whatsapp));
}

@JsonKey(includeFromJson: false, includeToJson: false)
@override
int get hashCode => Object.hash(runtimeType,id,shopName,handle,isVerified,ratingAvg,ratingCount,lat,lng,whatsapp);

@override
String toString() {
  return 'SellerSummary(id: $id, shopName: $shopName, handle: $handle, isVerified: $isVerified, ratingAvg: $ratingAvg, ratingCount: $ratingCount, lat: $lat, lng: $lng, whatsapp: $whatsapp)';
}


}

/// @nodoc
abstract mixin class _$SellerSummaryCopyWith<$Res> implements $SellerSummaryCopyWith<$Res> {
  factory _$SellerSummaryCopyWith(_SellerSummary value, $Res Function(_SellerSummary) _then) = __$SellerSummaryCopyWithImpl;
@override @useResult
$Res call({
 int id,@JsonKey(name: 'shop_name') String shopName, String handle,@JsonKey(name: 'is_verified') bool isVerified,@JsonKey(name: 'rating_avg') double ratingAvg,@JsonKey(name: 'rating_count') int ratingCount, double? lat, double? lng, String? whatsapp
});




}
/// @nodoc
class __$SellerSummaryCopyWithImpl<$Res>
    implements _$SellerSummaryCopyWith<$Res> {
  __$SellerSummaryCopyWithImpl(this._self, this._then);

  final _SellerSummary _self;
  final $Res Function(_SellerSummary) _then;

/// Create a copy of SellerSummary
/// with the given fields replaced by the non-null parameter values.
@override @pragma('vm:prefer-inline') $Res call({Object? id = null,Object? shopName = null,Object? handle = null,Object? isVerified = null,Object? ratingAvg = null,Object? ratingCount = null,Object? lat = freezed,Object? lng = freezed,Object? whatsapp = freezed,}) {
  return _then(_SellerSummary(
id: null == id ? _self.id : id // ignore: cast_nullable_to_non_nullable
as int,shopName: null == shopName ? _self.shopName : shopName // ignore: cast_nullable_to_non_nullable
as String,handle: null == handle ? _self.handle : handle // ignore: cast_nullable_to_non_nullable
as String,isVerified: null == isVerified ? _self.isVerified : isVerified // ignore: cast_nullable_to_non_nullable
as bool,ratingAvg: null == ratingAvg ? _self.ratingAvg : ratingAvg // ignore: cast_nullable_to_non_nullable
as double,ratingCount: null == ratingCount ? _self.ratingCount : ratingCount // ignore: cast_nullable_to_non_nullable
as int,lat: freezed == lat ? _self.lat : lat // ignore: cast_nullable_to_non_nullable
as double?,lng: freezed == lng ? _self.lng : lng // ignore: cast_nullable_to_non_nullable
as double?,whatsapp: freezed == whatsapp ? _self.whatsapp : whatsapp // ignore: cast_nullable_to_non_nullable
as String?,
  ));
}


}

// dart format on
