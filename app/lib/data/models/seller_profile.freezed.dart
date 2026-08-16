// GENERATED CODE - DO NOT MODIFY BY HAND
// coverage:ignore-file
// ignore_for_file: type=lint
// ignore_for_file: unused_element, deprecated_member_use, deprecated_member_use_from_same_package, use_function_type_syntax_for_parameters, unnecessary_const, avoid_init_to_null, invalid_override_different_default_values_named, prefer_expression_function_bodies, annotate_overrides, invalid_annotation_target, unnecessary_question_mark

part of 'seller_profile.dart';

// **************************************************************************
// FreezedGenerator
// **************************************************************************

// dart format off
T _$identity<T>(T value) => value;

/// @nodoc
mixin _$SellerProfile {

 int get id;@JsonKey(name: 'shop_name') String get shopName; String? get logo; String get handle; String? get bio; SokoniCategory? get category; String? get whatsapp; double? get lat; double? get lng; String? get address; String? get region; String? get district; String get status;@JsonKey(name: 'rejection_reason') String? get rejectionReason;@JsonKey(name: 'verified_at') DateTime? get verifiedAt;@JsonKey(name: 'rating_avg') double get ratingAvg;@JsonKey(name: 'rating_count') int get ratingCount;@JsonKey(name: 'customer_count') int get customerCount;@JsonKey(name: 'products_count') int get productsCount;@JsonKey(name: 'following_count') int get followingCount;@JsonKey(name: 'is_owner') bool get isOwner;@JsonKey(name: 'is_following') bool get isFollowing;@JsonKey(name: 'created_at') DateTime? get createdAt;
/// Create a copy of SellerProfile
/// with the given fields replaced by the non-null parameter values.
@JsonKey(includeFromJson: false, includeToJson: false)
@pragma('vm:prefer-inline')
$SellerProfileCopyWith<SellerProfile> get copyWith => _$SellerProfileCopyWithImpl<SellerProfile>(this as SellerProfile, _$identity);

  /// Serializes this SellerProfile to a JSON map.
  Map<String, dynamic> toJson();


@override
bool operator ==(Object other) {
  return identical(this, other) || (other.runtimeType == runtimeType&&other is SellerProfile&&(identical(other.id, id) || other.id == id)&&(identical(other.shopName, shopName) || other.shopName == shopName)&&(identical(other.logo, logo) || other.logo == logo)&&(identical(other.handle, handle) || other.handle == handle)&&(identical(other.bio, bio) || other.bio == bio)&&(identical(other.category, category) || other.category == category)&&(identical(other.whatsapp, whatsapp) || other.whatsapp == whatsapp)&&(identical(other.lat, lat) || other.lat == lat)&&(identical(other.lng, lng) || other.lng == lng)&&(identical(other.address, address) || other.address == address)&&(identical(other.region, region) || other.region == region)&&(identical(other.district, district) || other.district == district)&&(identical(other.status, status) || other.status == status)&&(identical(other.rejectionReason, rejectionReason) || other.rejectionReason == rejectionReason)&&(identical(other.verifiedAt, verifiedAt) || other.verifiedAt == verifiedAt)&&(identical(other.ratingAvg, ratingAvg) || other.ratingAvg == ratingAvg)&&(identical(other.ratingCount, ratingCount) || other.ratingCount == ratingCount)&&(identical(other.customerCount, customerCount) || other.customerCount == customerCount)&&(identical(other.productsCount, productsCount) || other.productsCount == productsCount)&&(identical(other.followingCount, followingCount) || other.followingCount == followingCount)&&(identical(other.isOwner, isOwner) || other.isOwner == isOwner)&&(identical(other.isFollowing, isFollowing) || other.isFollowing == isFollowing)&&(identical(other.createdAt, createdAt) || other.createdAt == createdAt));
}

@JsonKey(includeFromJson: false, includeToJson: false)
@override
int get hashCode => Object.hashAll([runtimeType,id,shopName,logo,handle,bio,category,whatsapp,lat,lng,address,region,district,status,rejectionReason,verifiedAt,ratingAvg,ratingCount,customerCount,productsCount,followingCount,isOwner,isFollowing,createdAt]);

@override
String toString() {
  return 'SellerProfile(id: $id, shopName: $shopName, logo: $logo, handle: $handle, bio: $bio, category: $category, whatsapp: $whatsapp, lat: $lat, lng: $lng, address: $address, region: $region, district: $district, status: $status, rejectionReason: $rejectionReason, verifiedAt: $verifiedAt, ratingAvg: $ratingAvg, ratingCount: $ratingCount, customerCount: $customerCount, productsCount: $productsCount, followingCount: $followingCount, isOwner: $isOwner, isFollowing: $isFollowing, createdAt: $createdAt)';
}


}

/// @nodoc
abstract mixin class $SellerProfileCopyWith<$Res>  {
  factory $SellerProfileCopyWith(SellerProfile value, $Res Function(SellerProfile) _then) = _$SellerProfileCopyWithImpl;
@useResult
$Res call({
 int id,@JsonKey(name: 'shop_name') String shopName, String? logo, String handle, String? bio, SokoniCategory? category, String? whatsapp, double? lat, double? lng, String? address, String? region, String? district, String status,@JsonKey(name: 'rejection_reason') String? rejectionReason,@JsonKey(name: 'verified_at') DateTime? verifiedAt,@JsonKey(name: 'rating_avg') double ratingAvg,@JsonKey(name: 'rating_count') int ratingCount,@JsonKey(name: 'customer_count') int customerCount,@JsonKey(name: 'products_count') int productsCount,@JsonKey(name: 'following_count') int followingCount,@JsonKey(name: 'is_owner') bool isOwner,@JsonKey(name: 'is_following') bool isFollowing,@JsonKey(name: 'created_at') DateTime? createdAt
});


$SokoniCategoryCopyWith<$Res>? get category;

}
/// @nodoc
class _$SellerProfileCopyWithImpl<$Res>
    implements $SellerProfileCopyWith<$Res> {
  _$SellerProfileCopyWithImpl(this._self, this._then);

  final SellerProfile _self;
  final $Res Function(SellerProfile) _then;

/// Create a copy of SellerProfile
/// with the given fields replaced by the non-null parameter values.
@pragma('vm:prefer-inline') @override $Res call({Object? id = null,Object? shopName = null,Object? logo = freezed,Object? handle = null,Object? bio = freezed,Object? category = freezed,Object? whatsapp = freezed,Object? lat = freezed,Object? lng = freezed,Object? address = freezed,Object? region = freezed,Object? district = freezed,Object? status = null,Object? rejectionReason = freezed,Object? verifiedAt = freezed,Object? ratingAvg = null,Object? ratingCount = null,Object? customerCount = null,Object? productsCount = null,Object? followingCount = null,Object? isOwner = null,Object? isFollowing = null,Object? createdAt = freezed,}) {
  return _then(_self.copyWith(
id: null == id ? _self.id : id // ignore: cast_nullable_to_non_nullable
as int,shopName: null == shopName ? _self.shopName : shopName // ignore: cast_nullable_to_non_nullable
as String,logo: freezed == logo ? _self.logo : logo // ignore: cast_nullable_to_non_nullable
as String?,handle: null == handle ? _self.handle : handle // ignore: cast_nullable_to_non_nullable
as String,bio: freezed == bio ? _self.bio : bio // ignore: cast_nullable_to_non_nullable
as String?,category: freezed == category ? _self.category : category // ignore: cast_nullable_to_non_nullable
as SokoniCategory?,whatsapp: freezed == whatsapp ? _self.whatsapp : whatsapp // ignore: cast_nullable_to_non_nullable
as String?,lat: freezed == lat ? _self.lat : lat // ignore: cast_nullable_to_non_nullable
as double?,lng: freezed == lng ? _self.lng : lng // ignore: cast_nullable_to_non_nullable
as double?,address: freezed == address ? _self.address : address // ignore: cast_nullable_to_non_nullable
as String?,region: freezed == region ? _self.region : region // ignore: cast_nullable_to_non_nullable
as String?,district: freezed == district ? _self.district : district // ignore: cast_nullable_to_non_nullable
as String?,status: null == status ? _self.status : status // ignore: cast_nullable_to_non_nullable
as String,rejectionReason: freezed == rejectionReason ? _self.rejectionReason : rejectionReason // ignore: cast_nullable_to_non_nullable
as String?,verifiedAt: freezed == verifiedAt ? _self.verifiedAt : verifiedAt // ignore: cast_nullable_to_non_nullable
as DateTime?,ratingAvg: null == ratingAvg ? _self.ratingAvg : ratingAvg // ignore: cast_nullable_to_non_nullable
as double,ratingCount: null == ratingCount ? _self.ratingCount : ratingCount // ignore: cast_nullable_to_non_nullable
as int,customerCount: null == customerCount ? _self.customerCount : customerCount // ignore: cast_nullable_to_non_nullable
as int,productsCount: null == productsCount ? _self.productsCount : productsCount // ignore: cast_nullable_to_non_nullable
as int,followingCount: null == followingCount ? _self.followingCount : followingCount // ignore: cast_nullable_to_non_nullable
as int,isOwner: null == isOwner ? _self.isOwner : isOwner // ignore: cast_nullable_to_non_nullable
as bool,isFollowing: null == isFollowing ? _self.isFollowing : isFollowing // ignore: cast_nullable_to_non_nullable
as bool,createdAt: freezed == createdAt ? _self.createdAt : createdAt // ignore: cast_nullable_to_non_nullable
as DateTime?,
  ));
}
/// Create a copy of SellerProfile
/// with the given fields replaced by the non-null parameter values.
@override
@pragma('vm:prefer-inline')
$SokoniCategoryCopyWith<$Res>? get category {
    if (_self.category == null) {
    return null;
  }

  return $SokoniCategoryCopyWith<$Res>(_self.category!, (value) {
    return _then(_self.copyWith(category: value));
  });
}
}


/// Adds pattern-matching-related methods to [SellerProfile].
extension SellerProfilePatterns on SellerProfile {
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

@optionalTypeArgs TResult maybeMap<TResult extends Object?>(TResult Function( _SellerProfile value)?  $default,{required TResult orElse(),}){
final _that = this;
switch (_that) {
case _SellerProfile() when $default != null:
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

@optionalTypeArgs TResult map<TResult extends Object?>(TResult Function( _SellerProfile value)  $default,){
final _that = this;
switch (_that) {
case _SellerProfile():
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

@optionalTypeArgs TResult? mapOrNull<TResult extends Object?>(TResult? Function( _SellerProfile value)?  $default,){
final _that = this;
switch (_that) {
case _SellerProfile() when $default != null:
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

@optionalTypeArgs TResult maybeWhen<TResult extends Object?>(TResult Function( int id, @JsonKey(name: 'shop_name')  String shopName,  String? logo,  String handle,  String? bio,  SokoniCategory? category,  String? whatsapp,  double? lat,  double? lng,  String? address,  String? region,  String? district,  String status, @JsonKey(name: 'rejection_reason')  String? rejectionReason, @JsonKey(name: 'verified_at')  DateTime? verifiedAt, @JsonKey(name: 'rating_avg')  double ratingAvg, @JsonKey(name: 'rating_count')  int ratingCount, @JsonKey(name: 'customer_count')  int customerCount, @JsonKey(name: 'products_count')  int productsCount, @JsonKey(name: 'following_count')  int followingCount, @JsonKey(name: 'is_owner')  bool isOwner, @JsonKey(name: 'is_following')  bool isFollowing, @JsonKey(name: 'created_at')  DateTime? createdAt)?  $default,{required TResult orElse(),}) {final _that = this;
switch (_that) {
case _SellerProfile() when $default != null:
return $default(_that.id,_that.shopName,_that.logo,_that.handle,_that.bio,_that.category,_that.whatsapp,_that.lat,_that.lng,_that.address,_that.region,_that.district,_that.status,_that.rejectionReason,_that.verifiedAt,_that.ratingAvg,_that.ratingCount,_that.customerCount,_that.productsCount,_that.followingCount,_that.isOwner,_that.isFollowing,_that.createdAt);case _:
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

@optionalTypeArgs TResult when<TResult extends Object?>(TResult Function( int id, @JsonKey(name: 'shop_name')  String shopName,  String? logo,  String handle,  String? bio,  SokoniCategory? category,  String? whatsapp,  double? lat,  double? lng,  String? address,  String? region,  String? district,  String status, @JsonKey(name: 'rejection_reason')  String? rejectionReason, @JsonKey(name: 'verified_at')  DateTime? verifiedAt, @JsonKey(name: 'rating_avg')  double ratingAvg, @JsonKey(name: 'rating_count')  int ratingCount, @JsonKey(name: 'customer_count')  int customerCount, @JsonKey(name: 'products_count')  int productsCount, @JsonKey(name: 'following_count')  int followingCount, @JsonKey(name: 'is_owner')  bool isOwner, @JsonKey(name: 'is_following')  bool isFollowing, @JsonKey(name: 'created_at')  DateTime? createdAt)  $default,) {final _that = this;
switch (_that) {
case _SellerProfile():
return $default(_that.id,_that.shopName,_that.logo,_that.handle,_that.bio,_that.category,_that.whatsapp,_that.lat,_that.lng,_that.address,_that.region,_that.district,_that.status,_that.rejectionReason,_that.verifiedAt,_that.ratingAvg,_that.ratingCount,_that.customerCount,_that.productsCount,_that.followingCount,_that.isOwner,_that.isFollowing,_that.createdAt);case _:
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

@optionalTypeArgs TResult? whenOrNull<TResult extends Object?>(TResult? Function( int id, @JsonKey(name: 'shop_name')  String shopName,  String? logo,  String handle,  String? bio,  SokoniCategory? category,  String? whatsapp,  double? lat,  double? lng,  String? address,  String? region,  String? district,  String status, @JsonKey(name: 'rejection_reason')  String? rejectionReason, @JsonKey(name: 'verified_at')  DateTime? verifiedAt, @JsonKey(name: 'rating_avg')  double ratingAvg, @JsonKey(name: 'rating_count')  int ratingCount, @JsonKey(name: 'customer_count')  int customerCount, @JsonKey(name: 'products_count')  int productsCount, @JsonKey(name: 'following_count')  int followingCount, @JsonKey(name: 'is_owner')  bool isOwner, @JsonKey(name: 'is_following')  bool isFollowing, @JsonKey(name: 'created_at')  DateTime? createdAt)?  $default,) {final _that = this;
switch (_that) {
case _SellerProfile() when $default != null:
return $default(_that.id,_that.shopName,_that.logo,_that.handle,_that.bio,_that.category,_that.whatsapp,_that.lat,_that.lng,_that.address,_that.region,_that.district,_that.status,_that.rejectionReason,_that.verifiedAt,_that.ratingAvg,_that.ratingCount,_that.customerCount,_that.productsCount,_that.followingCount,_that.isOwner,_that.isFollowing,_that.createdAt);case _:
  return null;

}
}

}

/// @nodoc
@JsonSerializable()

class _SellerProfile implements SellerProfile {
  const _SellerProfile({required this.id, @JsonKey(name: 'shop_name') required this.shopName, this.logo, required this.handle, this.bio, this.category, this.whatsapp, this.lat, this.lng, this.address, this.region, this.district, this.status = 'pending', @JsonKey(name: 'rejection_reason') this.rejectionReason, @JsonKey(name: 'verified_at') this.verifiedAt, @JsonKey(name: 'rating_avg') this.ratingAvg = 0, @JsonKey(name: 'rating_count') this.ratingCount = 0, @JsonKey(name: 'customer_count') this.customerCount = 0, @JsonKey(name: 'products_count') this.productsCount = 0, @JsonKey(name: 'following_count') this.followingCount = 0, @JsonKey(name: 'is_owner') this.isOwner = false, @JsonKey(name: 'is_following') this.isFollowing = false, @JsonKey(name: 'created_at') this.createdAt});
  factory _SellerProfile.fromJson(Map<String, dynamic> json) => _$SellerProfileFromJson(json);

@override final  int id;
@override@JsonKey(name: 'shop_name') final  String shopName;
@override final  String? logo;
@override final  String handle;
@override final  String? bio;
@override final  SokoniCategory? category;
@override final  String? whatsapp;
@override final  double? lat;
@override final  double? lng;
@override final  String? address;
@override final  String? region;
@override final  String? district;
@override@JsonKey() final  String status;
@override@JsonKey(name: 'rejection_reason') final  String? rejectionReason;
@override@JsonKey(name: 'verified_at') final  DateTime? verifiedAt;
@override@JsonKey(name: 'rating_avg') final  double ratingAvg;
@override@JsonKey(name: 'rating_count') final  int ratingCount;
@override@JsonKey(name: 'customer_count') final  int customerCount;
@override@JsonKey(name: 'products_count') final  int productsCount;
@override@JsonKey(name: 'following_count') final  int followingCount;
@override@JsonKey(name: 'is_owner') final  bool isOwner;
@override@JsonKey(name: 'is_following') final  bool isFollowing;
@override@JsonKey(name: 'created_at') final  DateTime? createdAt;

/// Create a copy of SellerProfile
/// with the given fields replaced by the non-null parameter values.
@override @JsonKey(includeFromJson: false, includeToJson: false)
@pragma('vm:prefer-inline')
_$SellerProfileCopyWith<_SellerProfile> get copyWith => __$SellerProfileCopyWithImpl<_SellerProfile>(this, _$identity);

@override
Map<String, dynamic> toJson() {
  return _$SellerProfileToJson(this, );
}

@override
bool operator ==(Object other) {
  return identical(this, other) || (other.runtimeType == runtimeType&&other is _SellerProfile&&(identical(other.id, id) || other.id == id)&&(identical(other.shopName, shopName) || other.shopName == shopName)&&(identical(other.logo, logo) || other.logo == logo)&&(identical(other.handle, handle) || other.handle == handle)&&(identical(other.bio, bio) || other.bio == bio)&&(identical(other.category, category) || other.category == category)&&(identical(other.whatsapp, whatsapp) || other.whatsapp == whatsapp)&&(identical(other.lat, lat) || other.lat == lat)&&(identical(other.lng, lng) || other.lng == lng)&&(identical(other.address, address) || other.address == address)&&(identical(other.region, region) || other.region == region)&&(identical(other.district, district) || other.district == district)&&(identical(other.status, status) || other.status == status)&&(identical(other.rejectionReason, rejectionReason) || other.rejectionReason == rejectionReason)&&(identical(other.verifiedAt, verifiedAt) || other.verifiedAt == verifiedAt)&&(identical(other.ratingAvg, ratingAvg) || other.ratingAvg == ratingAvg)&&(identical(other.ratingCount, ratingCount) || other.ratingCount == ratingCount)&&(identical(other.customerCount, customerCount) || other.customerCount == customerCount)&&(identical(other.productsCount, productsCount) || other.productsCount == productsCount)&&(identical(other.followingCount, followingCount) || other.followingCount == followingCount)&&(identical(other.isOwner, isOwner) || other.isOwner == isOwner)&&(identical(other.isFollowing, isFollowing) || other.isFollowing == isFollowing)&&(identical(other.createdAt, createdAt) || other.createdAt == createdAt));
}

@JsonKey(includeFromJson: false, includeToJson: false)
@override
int get hashCode => Object.hashAll([runtimeType,id,shopName,logo,handle,bio,category,whatsapp,lat,lng,address,region,district,status,rejectionReason,verifiedAt,ratingAvg,ratingCount,customerCount,productsCount,followingCount,isOwner,isFollowing,createdAt]);

@override
String toString() {
  return 'SellerProfile(id: $id, shopName: $shopName, logo: $logo, handle: $handle, bio: $bio, category: $category, whatsapp: $whatsapp, lat: $lat, lng: $lng, address: $address, region: $region, district: $district, status: $status, rejectionReason: $rejectionReason, verifiedAt: $verifiedAt, ratingAvg: $ratingAvg, ratingCount: $ratingCount, customerCount: $customerCount, productsCount: $productsCount, followingCount: $followingCount, isOwner: $isOwner, isFollowing: $isFollowing, createdAt: $createdAt)';
}


}

/// @nodoc
abstract mixin class _$SellerProfileCopyWith<$Res> implements $SellerProfileCopyWith<$Res> {
  factory _$SellerProfileCopyWith(_SellerProfile value, $Res Function(_SellerProfile) _then) = __$SellerProfileCopyWithImpl;
@override @useResult
$Res call({
 int id,@JsonKey(name: 'shop_name') String shopName, String? logo, String handle, String? bio, SokoniCategory? category, String? whatsapp, double? lat, double? lng, String? address, String? region, String? district, String status,@JsonKey(name: 'rejection_reason') String? rejectionReason,@JsonKey(name: 'verified_at') DateTime? verifiedAt,@JsonKey(name: 'rating_avg') double ratingAvg,@JsonKey(name: 'rating_count') int ratingCount,@JsonKey(name: 'customer_count') int customerCount,@JsonKey(name: 'products_count') int productsCount,@JsonKey(name: 'following_count') int followingCount,@JsonKey(name: 'is_owner') bool isOwner,@JsonKey(name: 'is_following') bool isFollowing,@JsonKey(name: 'created_at') DateTime? createdAt
});


@override $SokoniCategoryCopyWith<$Res>? get category;

}
/// @nodoc
class __$SellerProfileCopyWithImpl<$Res>
    implements _$SellerProfileCopyWith<$Res> {
  __$SellerProfileCopyWithImpl(this._self, this._then);

  final _SellerProfile _self;
  final $Res Function(_SellerProfile) _then;

/// Create a copy of SellerProfile
/// with the given fields replaced by the non-null parameter values.
@override @pragma('vm:prefer-inline') $Res call({Object? id = null,Object? shopName = null,Object? logo = freezed,Object? handle = null,Object? bio = freezed,Object? category = freezed,Object? whatsapp = freezed,Object? lat = freezed,Object? lng = freezed,Object? address = freezed,Object? region = freezed,Object? district = freezed,Object? status = null,Object? rejectionReason = freezed,Object? verifiedAt = freezed,Object? ratingAvg = null,Object? ratingCount = null,Object? customerCount = null,Object? productsCount = null,Object? followingCount = null,Object? isOwner = null,Object? isFollowing = null,Object? createdAt = freezed,}) {
  return _then(_SellerProfile(
id: null == id ? _self.id : id // ignore: cast_nullable_to_non_nullable
as int,shopName: null == shopName ? _self.shopName : shopName // ignore: cast_nullable_to_non_nullable
as String,logo: freezed == logo ? _self.logo : logo // ignore: cast_nullable_to_non_nullable
as String?,handle: null == handle ? _self.handle : handle // ignore: cast_nullable_to_non_nullable
as String,bio: freezed == bio ? _self.bio : bio // ignore: cast_nullable_to_non_nullable
as String?,category: freezed == category ? _self.category : category // ignore: cast_nullable_to_non_nullable
as SokoniCategory?,whatsapp: freezed == whatsapp ? _self.whatsapp : whatsapp // ignore: cast_nullable_to_non_nullable
as String?,lat: freezed == lat ? _self.lat : lat // ignore: cast_nullable_to_non_nullable
as double?,lng: freezed == lng ? _self.lng : lng // ignore: cast_nullable_to_non_nullable
as double?,address: freezed == address ? _self.address : address // ignore: cast_nullable_to_non_nullable
as String?,region: freezed == region ? _self.region : region // ignore: cast_nullable_to_non_nullable
as String?,district: freezed == district ? _self.district : district // ignore: cast_nullable_to_non_nullable
as String?,status: null == status ? _self.status : status // ignore: cast_nullable_to_non_nullable
as String,rejectionReason: freezed == rejectionReason ? _self.rejectionReason : rejectionReason // ignore: cast_nullable_to_non_nullable
as String?,verifiedAt: freezed == verifiedAt ? _self.verifiedAt : verifiedAt // ignore: cast_nullable_to_non_nullable
as DateTime?,ratingAvg: null == ratingAvg ? _self.ratingAvg : ratingAvg // ignore: cast_nullable_to_non_nullable
as double,ratingCount: null == ratingCount ? _self.ratingCount : ratingCount // ignore: cast_nullable_to_non_nullable
as int,customerCount: null == customerCount ? _self.customerCount : customerCount // ignore: cast_nullable_to_non_nullable
as int,productsCount: null == productsCount ? _self.productsCount : productsCount // ignore: cast_nullable_to_non_nullable
as int,followingCount: null == followingCount ? _self.followingCount : followingCount // ignore: cast_nullable_to_non_nullable
as int,isOwner: null == isOwner ? _self.isOwner : isOwner // ignore: cast_nullable_to_non_nullable
as bool,isFollowing: null == isFollowing ? _self.isFollowing : isFollowing // ignore: cast_nullable_to_non_nullable
as bool,createdAt: freezed == createdAt ? _self.createdAt : createdAt // ignore: cast_nullable_to_non_nullable
as DateTime?,
  ));
}

/// Create a copy of SellerProfile
/// with the given fields replaced by the non-null parameter values.
@override
@pragma('vm:prefer-inline')
$SokoniCategoryCopyWith<$Res>? get category {
    if (_self.category == null) {
    return null;
  }

  return $SokoniCategoryCopyWith<$Res>(_self.category!, (value) {
    return _then(_self.copyWith(category: value));
  });
}
}

// dart format on
