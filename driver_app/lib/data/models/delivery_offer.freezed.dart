// GENERATED CODE - DO NOT MODIFY BY HAND
// coverage:ignore-file
// ignore_for_file: type=lint
// ignore_for_file: unused_element, deprecated_member_use, deprecated_member_use_from_same_package, use_function_type_syntax_for_parameters, unnecessary_const, avoid_init_to_null, invalid_override_different_default_values_named, prefer_expression_function_bodies, annotate_overrides, invalid_annotation_target, unnecessary_question_mark

part of 'delivery_offer.dart';

// **************************************************************************
// FreezedGenerator
// **************************************************************************

// dart format off
T _$identity<T>(T value) => value;

/// @nodoc
mixin _$DeliveryOffer {

@JsonKey(name: 'job_id') int get jobId;@JsonKey(name: 'offered_at') DateTime get offeredAt; int get wave;@JsonKey(name: 'distance_to_shop_km') double get distanceToShopKm;@JsonKey(name: 'trip_km') double get tripKm;@JsonKey(name: 'driver_earnings') int get driverEarnings; int get fee;@JsonKey(name: 'collect_cash') int get collectCash;@JsonKey(name: 'items_count') int get itemsCount; OfferShop get shop;@JsonKey(name: 'dropoff_area') LatLngJson get dropoffArea;
/// Create a copy of DeliveryOffer
/// with the given fields replaced by the non-null parameter values.
@JsonKey(includeFromJson: false, includeToJson: false)
@pragma('vm:prefer-inline')
$DeliveryOfferCopyWith<DeliveryOffer> get copyWith => _$DeliveryOfferCopyWithImpl<DeliveryOffer>(this as DeliveryOffer, _$identity);

  /// Serializes this DeliveryOffer to a JSON map.
  Map<String, dynamic> toJson();


@override
bool operator ==(Object other) {
  return identical(this, other) || (other.runtimeType == runtimeType&&other is DeliveryOffer&&(identical(other.jobId, jobId) || other.jobId == jobId)&&(identical(other.offeredAt, offeredAt) || other.offeredAt == offeredAt)&&(identical(other.wave, wave) || other.wave == wave)&&(identical(other.distanceToShopKm, distanceToShopKm) || other.distanceToShopKm == distanceToShopKm)&&(identical(other.tripKm, tripKm) || other.tripKm == tripKm)&&(identical(other.driverEarnings, driverEarnings) || other.driverEarnings == driverEarnings)&&(identical(other.fee, fee) || other.fee == fee)&&(identical(other.collectCash, collectCash) || other.collectCash == collectCash)&&(identical(other.itemsCount, itemsCount) || other.itemsCount == itemsCount)&&(identical(other.shop, shop) || other.shop == shop)&&(identical(other.dropoffArea, dropoffArea) || other.dropoffArea == dropoffArea));
}

@JsonKey(includeFromJson: false, includeToJson: false)
@override
int get hashCode => Object.hash(runtimeType,jobId,offeredAt,wave,distanceToShopKm,tripKm,driverEarnings,fee,collectCash,itemsCount,shop,dropoffArea);

@override
String toString() {
  return 'DeliveryOffer(jobId: $jobId, offeredAt: $offeredAt, wave: $wave, distanceToShopKm: $distanceToShopKm, tripKm: $tripKm, driverEarnings: $driverEarnings, fee: $fee, collectCash: $collectCash, itemsCount: $itemsCount, shop: $shop, dropoffArea: $dropoffArea)';
}


}

/// @nodoc
abstract mixin class $DeliveryOfferCopyWith<$Res>  {
  factory $DeliveryOfferCopyWith(DeliveryOffer value, $Res Function(DeliveryOffer) _then) = _$DeliveryOfferCopyWithImpl;
@useResult
$Res call({
@JsonKey(name: 'job_id') int jobId,@JsonKey(name: 'offered_at') DateTime offeredAt, int wave,@JsonKey(name: 'distance_to_shop_km') double distanceToShopKm,@JsonKey(name: 'trip_km') double tripKm,@JsonKey(name: 'driver_earnings') int driverEarnings, int fee,@JsonKey(name: 'collect_cash') int collectCash,@JsonKey(name: 'items_count') int itemsCount, OfferShop shop,@JsonKey(name: 'dropoff_area') LatLngJson dropoffArea
});


$OfferShopCopyWith<$Res> get shop;$LatLngJsonCopyWith<$Res> get dropoffArea;

}
/// @nodoc
class _$DeliveryOfferCopyWithImpl<$Res>
    implements $DeliveryOfferCopyWith<$Res> {
  _$DeliveryOfferCopyWithImpl(this._self, this._then);

  final DeliveryOffer _self;
  final $Res Function(DeliveryOffer) _then;

/// Create a copy of DeliveryOffer
/// with the given fields replaced by the non-null parameter values.
@pragma('vm:prefer-inline') @override $Res call({Object? jobId = null,Object? offeredAt = null,Object? wave = null,Object? distanceToShopKm = null,Object? tripKm = null,Object? driverEarnings = null,Object? fee = null,Object? collectCash = null,Object? itemsCount = null,Object? shop = null,Object? dropoffArea = null,}) {
  return _then(_self.copyWith(
jobId: null == jobId ? _self.jobId : jobId // ignore: cast_nullable_to_non_nullable
as int,offeredAt: null == offeredAt ? _self.offeredAt : offeredAt // ignore: cast_nullable_to_non_nullable
as DateTime,wave: null == wave ? _self.wave : wave // ignore: cast_nullable_to_non_nullable
as int,distanceToShopKm: null == distanceToShopKm ? _self.distanceToShopKm : distanceToShopKm // ignore: cast_nullable_to_non_nullable
as double,tripKm: null == tripKm ? _self.tripKm : tripKm // ignore: cast_nullable_to_non_nullable
as double,driverEarnings: null == driverEarnings ? _self.driverEarnings : driverEarnings // ignore: cast_nullable_to_non_nullable
as int,fee: null == fee ? _self.fee : fee // ignore: cast_nullable_to_non_nullable
as int,collectCash: null == collectCash ? _self.collectCash : collectCash // ignore: cast_nullable_to_non_nullable
as int,itemsCount: null == itemsCount ? _self.itemsCount : itemsCount // ignore: cast_nullable_to_non_nullable
as int,shop: null == shop ? _self.shop : shop // ignore: cast_nullable_to_non_nullable
as OfferShop,dropoffArea: null == dropoffArea ? _self.dropoffArea : dropoffArea // ignore: cast_nullable_to_non_nullable
as LatLngJson,
  ));
}
/// Create a copy of DeliveryOffer
/// with the given fields replaced by the non-null parameter values.
@override
@pragma('vm:prefer-inline')
$OfferShopCopyWith<$Res> get shop {
  
  return $OfferShopCopyWith<$Res>(_self.shop, (value) {
    return _then(_self.copyWith(shop: value));
  });
}/// Create a copy of DeliveryOffer
/// with the given fields replaced by the non-null parameter values.
@override
@pragma('vm:prefer-inline')
$LatLngJsonCopyWith<$Res> get dropoffArea {
  
  return $LatLngJsonCopyWith<$Res>(_self.dropoffArea, (value) {
    return _then(_self.copyWith(dropoffArea: value));
  });
}
}


/// Adds pattern-matching-related methods to [DeliveryOffer].
extension DeliveryOfferPatterns on DeliveryOffer {
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

@optionalTypeArgs TResult maybeMap<TResult extends Object?>(TResult Function( _DeliveryOffer value)?  $default,{required TResult orElse(),}){
final _that = this;
switch (_that) {
case _DeliveryOffer() when $default != null:
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

@optionalTypeArgs TResult map<TResult extends Object?>(TResult Function( _DeliveryOffer value)  $default,){
final _that = this;
switch (_that) {
case _DeliveryOffer():
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

@optionalTypeArgs TResult? mapOrNull<TResult extends Object?>(TResult? Function( _DeliveryOffer value)?  $default,){
final _that = this;
switch (_that) {
case _DeliveryOffer() when $default != null:
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

@optionalTypeArgs TResult maybeWhen<TResult extends Object?>(TResult Function(@JsonKey(name: 'job_id')  int jobId, @JsonKey(name: 'offered_at')  DateTime offeredAt,  int wave, @JsonKey(name: 'distance_to_shop_km')  double distanceToShopKm, @JsonKey(name: 'trip_km')  double tripKm, @JsonKey(name: 'driver_earnings')  int driverEarnings,  int fee, @JsonKey(name: 'collect_cash')  int collectCash, @JsonKey(name: 'items_count')  int itemsCount,  OfferShop shop, @JsonKey(name: 'dropoff_area')  LatLngJson dropoffArea)?  $default,{required TResult orElse(),}) {final _that = this;
switch (_that) {
case _DeliveryOffer() when $default != null:
return $default(_that.jobId,_that.offeredAt,_that.wave,_that.distanceToShopKm,_that.tripKm,_that.driverEarnings,_that.fee,_that.collectCash,_that.itemsCount,_that.shop,_that.dropoffArea);case _:
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

@optionalTypeArgs TResult when<TResult extends Object?>(TResult Function(@JsonKey(name: 'job_id')  int jobId, @JsonKey(name: 'offered_at')  DateTime offeredAt,  int wave, @JsonKey(name: 'distance_to_shop_km')  double distanceToShopKm, @JsonKey(name: 'trip_km')  double tripKm, @JsonKey(name: 'driver_earnings')  int driverEarnings,  int fee, @JsonKey(name: 'collect_cash')  int collectCash, @JsonKey(name: 'items_count')  int itemsCount,  OfferShop shop, @JsonKey(name: 'dropoff_area')  LatLngJson dropoffArea)  $default,) {final _that = this;
switch (_that) {
case _DeliveryOffer():
return $default(_that.jobId,_that.offeredAt,_that.wave,_that.distanceToShopKm,_that.tripKm,_that.driverEarnings,_that.fee,_that.collectCash,_that.itemsCount,_that.shop,_that.dropoffArea);case _:
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

@optionalTypeArgs TResult? whenOrNull<TResult extends Object?>(TResult? Function(@JsonKey(name: 'job_id')  int jobId, @JsonKey(name: 'offered_at')  DateTime offeredAt,  int wave, @JsonKey(name: 'distance_to_shop_km')  double distanceToShopKm, @JsonKey(name: 'trip_km')  double tripKm, @JsonKey(name: 'driver_earnings')  int driverEarnings,  int fee, @JsonKey(name: 'collect_cash')  int collectCash, @JsonKey(name: 'items_count')  int itemsCount,  OfferShop shop, @JsonKey(name: 'dropoff_area')  LatLngJson dropoffArea)?  $default,) {final _that = this;
switch (_that) {
case _DeliveryOffer() when $default != null:
return $default(_that.jobId,_that.offeredAt,_that.wave,_that.distanceToShopKm,_that.tripKm,_that.driverEarnings,_that.fee,_that.collectCash,_that.itemsCount,_that.shop,_that.dropoffArea);case _:
  return null;

}
}

}

/// @nodoc
@JsonSerializable()

class _DeliveryOffer implements DeliveryOffer {
  const _DeliveryOffer({@JsonKey(name: 'job_id') required this.jobId, @JsonKey(name: 'offered_at') required this.offeredAt, this.wave = 1, @JsonKey(name: 'distance_to_shop_km') this.distanceToShopKm = 0, @JsonKey(name: 'trip_km') this.tripKm = 0, @JsonKey(name: 'driver_earnings') required this.driverEarnings, required this.fee, @JsonKey(name: 'collect_cash') this.collectCash = 0, @JsonKey(name: 'items_count') this.itemsCount = 0, required this.shop, @JsonKey(name: 'dropoff_area') required this.dropoffArea});
  factory _DeliveryOffer.fromJson(Map<String, dynamic> json) => _$DeliveryOfferFromJson(json);

@override@JsonKey(name: 'job_id') final  int jobId;
@override@JsonKey(name: 'offered_at') final  DateTime offeredAt;
@override@JsonKey() final  int wave;
@override@JsonKey(name: 'distance_to_shop_km') final  double distanceToShopKm;
@override@JsonKey(name: 'trip_km') final  double tripKm;
@override@JsonKey(name: 'driver_earnings') final  int driverEarnings;
@override final  int fee;
@override@JsonKey(name: 'collect_cash') final  int collectCash;
@override@JsonKey(name: 'items_count') final  int itemsCount;
@override final  OfferShop shop;
@override@JsonKey(name: 'dropoff_area') final  LatLngJson dropoffArea;

/// Create a copy of DeliveryOffer
/// with the given fields replaced by the non-null parameter values.
@override @JsonKey(includeFromJson: false, includeToJson: false)
@pragma('vm:prefer-inline')
_$DeliveryOfferCopyWith<_DeliveryOffer> get copyWith => __$DeliveryOfferCopyWithImpl<_DeliveryOffer>(this, _$identity);

@override
Map<String, dynamic> toJson() {
  return _$DeliveryOfferToJson(this, );
}

@override
bool operator ==(Object other) {
  return identical(this, other) || (other.runtimeType == runtimeType&&other is _DeliveryOffer&&(identical(other.jobId, jobId) || other.jobId == jobId)&&(identical(other.offeredAt, offeredAt) || other.offeredAt == offeredAt)&&(identical(other.wave, wave) || other.wave == wave)&&(identical(other.distanceToShopKm, distanceToShopKm) || other.distanceToShopKm == distanceToShopKm)&&(identical(other.tripKm, tripKm) || other.tripKm == tripKm)&&(identical(other.driverEarnings, driverEarnings) || other.driverEarnings == driverEarnings)&&(identical(other.fee, fee) || other.fee == fee)&&(identical(other.collectCash, collectCash) || other.collectCash == collectCash)&&(identical(other.itemsCount, itemsCount) || other.itemsCount == itemsCount)&&(identical(other.shop, shop) || other.shop == shop)&&(identical(other.dropoffArea, dropoffArea) || other.dropoffArea == dropoffArea));
}

@JsonKey(includeFromJson: false, includeToJson: false)
@override
int get hashCode => Object.hash(runtimeType,jobId,offeredAt,wave,distanceToShopKm,tripKm,driverEarnings,fee,collectCash,itemsCount,shop,dropoffArea);

@override
String toString() {
  return 'DeliveryOffer(jobId: $jobId, offeredAt: $offeredAt, wave: $wave, distanceToShopKm: $distanceToShopKm, tripKm: $tripKm, driverEarnings: $driverEarnings, fee: $fee, collectCash: $collectCash, itemsCount: $itemsCount, shop: $shop, dropoffArea: $dropoffArea)';
}


}

/// @nodoc
abstract mixin class _$DeliveryOfferCopyWith<$Res> implements $DeliveryOfferCopyWith<$Res> {
  factory _$DeliveryOfferCopyWith(_DeliveryOffer value, $Res Function(_DeliveryOffer) _then) = __$DeliveryOfferCopyWithImpl;
@override @useResult
$Res call({
@JsonKey(name: 'job_id') int jobId,@JsonKey(name: 'offered_at') DateTime offeredAt, int wave,@JsonKey(name: 'distance_to_shop_km') double distanceToShopKm,@JsonKey(name: 'trip_km') double tripKm,@JsonKey(name: 'driver_earnings') int driverEarnings, int fee,@JsonKey(name: 'collect_cash') int collectCash,@JsonKey(name: 'items_count') int itemsCount, OfferShop shop,@JsonKey(name: 'dropoff_area') LatLngJson dropoffArea
});


@override $OfferShopCopyWith<$Res> get shop;@override $LatLngJsonCopyWith<$Res> get dropoffArea;

}
/// @nodoc
class __$DeliveryOfferCopyWithImpl<$Res>
    implements _$DeliveryOfferCopyWith<$Res> {
  __$DeliveryOfferCopyWithImpl(this._self, this._then);

  final _DeliveryOffer _self;
  final $Res Function(_DeliveryOffer) _then;

/// Create a copy of DeliveryOffer
/// with the given fields replaced by the non-null parameter values.
@override @pragma('vm:prefer-inline') $Res call({Object? jobId = null,Object? offeredAt = null,Object? wave = null,Object? distanceToShopKm = null,Object? tripKm = null,Object? driverEarnings = null,Object? fee = null,Object? collectCash = null,Object? itemsCount = null,Object? shop = null,Object? dropoffArea = null,}) {
  return _then(_DeliveryOffer(
jobId: null == jobId ? _self.jobId : jobId // ignore: cast_nullable_to_non_nullable
as int,offeredAt: null == offeredAt ? _self.offeredAt : offeredAt // ignore: cast_nullable_to_non_nullable
as DateTime,wave: null == wave ? _self.wave : wave // ignore: cast_nullable_to_non_nullable
as int,distanceToShopKm: null == distanceToShopKm ? _self.distanceToShopKm : distanceToShopKm // ignore: cast_nullable_to_non_nullable
as double,tripKm: null == tripKm ? _self.tripKm : tripKm // ignore: cast_nullable_to_non_nullable
as double,driverEarnings: null == driverEarnings ? _self.driverEarnings : driverEarnings // ignore: cast_nullable_to_non_nullable
as int,fee: null == fee ? _self.fee : fee // ignore: cast_nullable_to_non_nullable
as int,collectCash: null == collectCash ? _self.collectCash : collectCash // ignore: cast_nullable_to_non_nullable
as int,itemsCount: null == itemsCount ? _self.itemsCount : itemsCount // ignore: cast_nullable_to_non_nullable
as int,shop: null == shop ? _self.shop : shop // ignore: cast_nullable_to_non_nullable
as OfferShop,dropoffArea: null == dropoffArea ? _self.dropoffArea : dropoffArea // ignore: cast_nullable_to_non_nullable
as LatLngJson,
  ));
}

/// Create a copy of DeliveryOffer
/// with the given fields replaced by the non-null parameter values.
@override
@pragma('vm:prefer-inline')
$OfferShopCopyWith<$Res> get shop {
  
  return $OfferShopCopyWith<$Res>(_self.shop, (value) {
    return _then(_self.copyWith(shop: value));
  });
}/// Create a copy of DeliveryOffer
/// with the given fields replaced by the non-null parameter values.
@override
@pragma('vm:prefer-inline')
$LatLngJsonCopyWith<$Res> get dropoffArea {
  
  return $LatLngJsonCopyWith<$Res>(_self.dropoffArea, (value) {
    return _then(_self.copyWith(dropoffArea: value));
  });
}
}


/// @nodoc
mixin _$OfferShop {

 String? get name; String? get district; double get lat; double get lng;
/// Create a copy of OfferShop
/// with the given fields replaced by the non-null parameter values.
@JsonKey(includeFromJson: false, includeToJson: false)
@pragma('vm:prefer-inline')
$OfferShopCopyWith<OfferShop> get copyWith => _$OfferShopCopyWithImpl<OfferShop>(this as OfferShop, _$identity);

  /// Serializes this OfferShop to a JSON map.
  Map<String, dynamic> toJson();


@override
bool operator ==(Object other) {
  return identical(this, other) || (other.runtimeType == runtimeType&&other is OfferShop&&(identical(other.name, name) || other.name == name)&&(identical(other.district, district) || other.district == district)&&(identical(other.lat, lat) || other.lat == lat)&&(identical(other.lng, lng) || other.lng == lng));
}

@JsonKey(includeFromJson: false, includeToJson: false)
@override
int get hashCode => Object.hash(runtimeType,name,district,lat,lng);

@override
String toString() {
  return 'OfferShop(name: $name, district: $district, lat: $lat, lng: $lng)';
}


}

/// @nodoc
abstract mixin class $OfferShopCopyWith<$Res>  {
  factory $OfferShopCopyWith(OfferShop value, $Res Function(OfferShop) _then) = _$OfferShopCopyWithImpl;
@useResult
$Res call({
 String? name, String? district, double lat, double lng
});




}
/// @nodoc
class _$OfferShopCopyWithImpl<$Res>
    implements $OfferShopCopyWith<$Res> {
  _$OfferShopCopyWithImpl(this._self, this._then);

  final OfferShop _self;
  final $Res Function(OfferShop) _then;

/// Create a copy of OfferShop
/// with the given fields replaced by the non-null parameter values.
@pragma('vm:prefer-inline') @override $Res call({Object? name = freezed,Object? district = freezed,Object? lat = null,Object? lng = null,}) {
  return _then(_self.copyWith(
name: freezed == name ? _self.name : name // ignore: cast_nullable_to_non_nullable
as String?,district: freezed == district ? _self.district : district // ignore: cast_nullable_to_non_nullable
as String?,lat: null == lat ? _self.lat : lat // ignore: cast_nullable_to_non_nullable
as double,lng: null == lng ? _self.lng : lng // ignore: cast_nullable_to_non_nullable
as double,
  ));
}

}


/// Adds pattern-matching-related methods to [OfferShop].
extension OfferShopPatterns on OfferShop {
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

@optionalTypeArgs TResult maybeMap<TResult extends Object?>(TResult Function( _OfferShop value)?  $default,{required TResult orElse(),}){
final _that = this;
switch (_that) {
case _OfferShop() when $default != null:
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

@optionalTypeArgs TResult map<TResult extends Object?>(TResult Function( _OfferShop value)  $default,){
final _that = this;
switch (_that) {
case _OfferShop():
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

@optionalTypeArgs TResult? mapOrNull<TResult extends Object?>(TResult? Function( _OfferShop value)?  $default,){
final _that = this;
switch (_that) {
case _OfferShop() when $default != null:
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

@optionalTypeArgs TResult maybeWhen<TResult extends Object?>(TResult Function( String? name,  String? district,  double lat,  double lng)?  $default,{required TResult orElse(),}) {final _that = this;
switch (_that) {
case _OfferShop() when $default != null:
return $default(_that.name,_that.district,_that.lat,_that.lng);case _:
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

@optionalTypeArgs TResult when<TResult extends Object?>(TResult Function( String? name,  String? district,  double lat,  double lng)  $default,) {final _that = this;
switch (_that) {
case _OfferShop():
return $default(_that.name,_that.district,_that.lat,_that.lng);case _:
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

@optionalTypeArgs TResult? whenOrNull<TResult extends Object?>(TResult? Function( String? name,  String? district,  double lat,  double lng)?  $default,) {final _that = this;
switch (_that) {
case _OfferShop() when $default != null:
return $default(_that.name,_that.district,_that.lat,_that.lng);case _:
  return null;

}
}

}

/// @nodoc
@JsonSerializable()

class _OfferShop implements OfferShop {
  const _OfferShop({this.name, this.district, required this.lat, required this.lng});
  factory _OfferShop.fromJson(Map<String, dynamic> json) => _$OfferShopFromJson(json);

@override final  String? name;
@override final  String? district;
@override final  double lat;
@override final  double lng;

/// Create a copy of OfferShop
/// with the given fields replaced by the non-null parameter values.
@override @JsonKey(includeFromJson: false, includeToJson: false)
@pragma('vm:prefer-inline')
_$OfferShopCopyWith<_OfferShop> get copyWith => __$OfferShopCopyWithImpl<_OfferShop>(this, _$identity);

@override
Map<String, dynamic> toJson() {
  return _$OfferShopToJson(this, );
}

@override
bool operator ==(Object other) {
  return identical(this, other) || (other.runtimeType == runtimeType&&other is _OfferShop&&(identical(other.name, name) || other.name == name)&&(identical(other.district, district) || other.district == district)&&(identical(other.lat, lat) || other.lat == lat)&&(identical(other.lng, lng) || other.lng == lng));
}

@JsonKey(includeFromJson: false, includeToJson: false)
@override
int get hashCode => Object.hash(runtimeType,name,district,lat,lng);

@override
String toString() {
  return 'OfferShop(name: $name, district: $district, lat: $lat, lng: $lng)';
}


}

/// @nodoc
abstract mixin class _$OfferShopCopyWith<$Res> implements $OfferShopCopyWith<$Res> {
  factory _$OfferShopCopyWith(_OfferShop value, $Res Function(_OfferShop) _then) = __$OfferShopCopyWithImpl;
@override @useResult
$Res call({
 String? name, String? district, double lat, double lng
});




}
/// @nodoc
class __$OfferShopCopyWithImpl<$Res>
    implements _$OfferShopCopyWith<$Res> {
  __$OfferShopCopyWithImpl(this._self, this._then);

  final _OfferShop _self;
  final $Res Function(_OfferShop) _then;

/// Create a copy of OfferShop
/// with the given fields replaced by the non-null parameter values.
@override @pragma('vm:prefer-inline') $Res call({Object? name = freezed,Object? district = freezed,Object? lat = null,Object? lng = null,}) {
  return _then(_OfferShop(
name: freezed == name ? _self.name : name // ignore: cast_nullable_to_non_nullable
as String?,district: freezed == district ? _self.district : district // ignore: cast_nullable_to_non_nullable
as String?,lat: null == lat ? _self.lat : lat // ignore: cast_nullable_to_non_nullable
as double,lng: null == lng ? _self.lng : lng // ignore: cast_nullable_to_non_nullable
as double,
  ));
}


}


/// @nodoc
mixin _$LatLngJson {

 double get lat; double get lng;
/// Create a copy of LatLngJson
/// with the given fields replaced by the non-null parameter values.
@JsonKey(includeFromJson: false, includeToJson: false)
@pragma('vm:prefer-inline')
$LatLngJsonCopyWith<LatLngJson> get copyWith => _$LatLngJsonCopyWithImpl<LatLngJson>(this as LatLngJson, _$identity);

  /// Serializes this LatLngJson to a JSON map.
  Map<String, dynamic> toJson();


@override
bool operator ==(Object other) {
  return identical(this, other) || (other.runtimeType == runtimeType&&other is LatLngJson&&(identical(other.lat, lat) || other.lat == lat)&&(identical(other.lng, lng) || other.lng == lng));
}

@JsonKey(includeFromJson: false, includeToJson: false)
@override
int get hashCode => Object.hash(runtimeType,lat,lng);

@override
String toString() {
  return 'LatLngJson(lat: $lat, lng: $lng)';
}


}

/// @nodoc
abstract mixin class $LatLngJsonCopyWith<$Res>  {
  factory $LatLngJsonCopyWith(LatLngJson value, $Res Function(LatLngJson) _then) = _$LatLngJsonCopyWithImpl;
@useResult
$Res call({
 double lat, double lng
});




}
/// @nodoc
class _$LatLngJsonCopyWithImpl<$Res>
    implements $LatLngJsonCopyWith<$Res> {
  _$LatLngJsonCopyWithImpl(this._self, this._then);

  final LatLngJson _self;
  final $Res Function(LatLngJson) _then;

/// Create a copy of LatLngJson
/// with the given fields replaced by the non-null parameter values.
@pragma('vm:prefer-inline') @override $Res call({Object? lat = null,Object? lng = null,}) {
  return _then(_self.copyWith(
lat: null == lat ? _self.lat : lat // ignore: cast_nullable_to_non_nullable
as double,lng: null == lng ? _self.lng : lng // ignore: cast_nullable_to_non_nullable
as double,
  ));
}

}


/// Adds pattern-matching-related methods to [LatLngJson].
extension LatLngJsonPatterns on LatLngJson {
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

@optionalTypeArgs TResult maybeMap<TResult extends Object?>(TResult Function( _LatLngJson value)?  $default,{required TResult orElse(),}){
final _that = this;
switch (_that) {
case _LatLngJson() when $default != null:
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

@optionalTypeArgs TResult map<TResult extends Object?>(TResult Function( _LatLngJson value)  $default,){
final _that = this;
switch (_that) {
case _LatLngJson():
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

@optionalTypeArgs TResult? mapOrNull<TResult extends Object?>(TResult? Function( _LatLngJson value)?  $default,){
final _that = this;
switch (_that) {
case _LatLngJson() when $default != null:
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

@optionalTypeArgs TResult maybeWhen<TResult extends Object?>(TResult Function( double lat,  double lng)?  $default,{required TResult orElse(),}) {final _that = this;
switch (_that) {
case _LatLngJson() when $default != null:
return $default(_that.lat,_that.lng);case _:
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

@optionalTypeArgs TResult when<TResult extends Object?>(TResult Function( double lat,  double lng)  $default,) {final _that = this;
switch (_that) {
case _LatLngJson():
return $default(_that.lat,_that.lng);case _:
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

@optionalTypeArgs TResult? whenOrNull<TResult extends Object?>(TResult? Function( double lat,  double lng)?  $default,) {final _that = this;
switch (_that) {
case _LatLngJson() when $default != null:
return $default(_that.lat,_that.lng);case _:
  return null;

}
}

}

/// @nodoc
@JsonSerializable()

class _LatLngJson implements LatLngJson {
  const _LatLngJson({required this.lat, required this.lng});
  factory _LatLngJson.fromJson(Map<String, dynamic> json) => _$LatLngJsonFromJson(json);

@override final  double lat;
@override final  double lng;

/// Create a copy of LatLngJson
/// with the given fields replaced by the non-null parameter values.
@override @JsonKey(includeFromJson: false, includeToJson: false)
@pragma('vm:prefer-inline')
_$LatLngJsonCopyWith<_LatLngJson> get copyWith => __$LatLngJsonCopyWithImpl<_LatLngJson>(this, _$identity);

@override
Map<String, dynamic> toJson() {
  return _$LatLngJsonToJson(this, );
}

@override
bool operator ==(Object other) {
  return identical(this, other) || (other.runtimeType == runtimeType&&other is _LatLngJson&&(identical(other.lat, lat) || other.lat == lat)&&(identical(other.lng, lng) || other.lng == lng));
}

@JsonKey(includeFromJson: false, includeToJson: false)
@override
int get hashCode => Object.hash(runtimeType,lat,lng);

@override
String toString() {
  return 'LatLngJson(lat: $lat, lng: $lng)';
}


}

/// @nodoc
abstract mixin class _$LatLngJsonCopyWith<$Res> implements $LatLngJsonCopyWith<$Res> {
  factory _$LatLngJsonCopyWith(_LatLngJson value, $Res Function(_LatLngJson) _then) = __$LatLngJsonCopyWithImpl;
@override @useResult
$Res call({
 double lat, double lng
});




}
/// @nodoc
class __$LatLngJsonCopyWithImpl<$Res>
    implements _$LatLngJsonCopyWith<$Res> {
  __$LatLngJsonCopyWithImpl(this._self, this._then);

  final _LatLngJson _self;
  final $Res Function(_LatLngJson) _then;

/// Create a copy of LatLngJson
/// with the given fields replaced by the non-null parameter values.
@override @pragma('vm:prefer-inline') $Res call({Object? lat = null,Object? lng = null,}) {
  return _then(_LatLngJson(
lat: null == lat ? _self.lat : lat // ignore: cast_nullable_to_non_nullable
as double,lng: null == lng ? _self.lng : lng // ignore: cast_nullable_to_non_nullable
as double,
  ));
}


}

// dart format on
