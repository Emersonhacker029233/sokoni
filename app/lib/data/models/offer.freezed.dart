// GENERATED CODE - DO NOT MODIFY BY HAND
// coverage:ignore-file
// ignore_for_file: type=lint
// ignore_for_file: unused_element, deprecated_member_use, deprecated_member_use_from_same_package, use_function_type_syntax_for_parameters, unnecessary_const, avoid_init_to_null, invalid_override_different_default_values_named, prefer_expression_function_bodies, annotate_overrides, invalid_annotation_target, unnecessary_question_mark

part of 'offer.dart';

// **************************************************************************
// FreezedGenerator
// **************************************************************************

// dart format off
T _$identity<T>(T value) => value;

/// @nodoc
mixin _$Offer {

 int get id;@JsonKey(name: 'discount_type') String get discountType;@JsonKey(name: 'discount_value') double get discountValue;@JsonKey(name: 'price_snapshot') double get priceSnapshot;@JsonKey(name: 'discounted_price') int get discountedPrice;@JsonKey(name: 'starts_at') DateTime get startsAt;@JsonKey(name: 'ends_at') DateTime get endsAt;@JsonKey(name: 'is_active') bool get isActive; SellerSummary? get seller; ProductSummary? get product;@JsonKey(name: 'created_at') DateTime? get createdAt;
/// Create a copy of Offer
/// with the given fields replaced by the non-null parameter values.
@JsonKey(includeFromJson: false, includeToJson: false)
@pragma('vm:prefer-inline')
$OfferCopyWith<Offer> get copyWith => _$OfferCopyWithImpl<Offer>(this as Offer, _$identity);

  /// Serializes this Offer to a JSON map.
  Map<String, dynamic> toJson();


@override
bool operator ==(Object other) {
  return identical(this, other) || (other.runtimeType == runtimeType&&other is Offer&&(identical(other.id, id) || other.id == id)&&(identical(other.discountType, discountType) || other.discountType == discountType)&&(identical(other.discountValue, discountValue) || other.discountValue == discountValue)&&(identical(other.priceSnapshot, priceSnapshot) || other.priceSnapshot == priceSnapshot)&&(identical(other.discountedPrice, discountedPrice) || other.discountedPrice == discountedPrice)&&(identical(other.startsAt, startsAt) || other.startsAt == startsAt)&&(identical(other.endsAt, endsAt) || other.endsAt == endsAt)&&(identical(other.isActive, isActive) || other.isActive == isActive)&&(identical(other.seller, seller) || other.seller == seller)&&(identical(other.product, product) || other.product == product)&&(identical(other.createdAt, createdAt) || other.createdAt == createdAt));
}

@JsonKey(includeFromJson: false, includeToJson: false)
@override
int get hashCode => Object.hash(runtimeType,id,discountType,discountValue,priceSnapshot,discountedPrice,startsAt,endsAt,isActive,seller,product,createdAt);

@override
String toString() {
  return 'Offer(id: $id, discountType: $discountType, discountValue: $discountValue, priceSnapshot: $priceSnapshot, discountedPrice: $discountedPrice, startsAt: $startsAt, endsAt: $endsAt, isActive: $isActive, seller: $seller, product: $product, createdAt: $createdAt)';
}


}

/// @nodoc
abstract mixin class $OfferCopyWith<$Res>  {
  factory $OfferCopyWith(Offer value, $Res Function(Offer) _then) = _$OfferCopyWithImpl;
@useResult
$Res call({
 int id,@JsonKey(name: 'discount_type') String discountType,@JsonKey(name: 'discount_value') double discountValue,@JsonKey(name: 'price_snapshot') double priceSnapshot,@JsonKey(name: 'discounted_price') int discountedPrice,@JsonKey(name: 'starts_at') DateTime startsAt,@JsonKey(name: 'ends_at') DateTime endsAt,@JsonKey(name: 'is_active') bool isActive, SellerSummary? seller, ProductSummary? product,@JsonKey(name: 'created_at') DateTime? createdAt
});


$SellerSummaryCopyWith<$Res>? get seller;$ProductSummaryCopyWith<$Res>? get product;

}
/// @nodoc
class _$OfferCopyWithImpl<$Res>
    implements $OfferCopyWith<$Res> {
  _$OfferCopyWithImpl(this._self, this._then);

  final Offer _self;
  final $Res Function(Offer) _then;

/// Create a copy of Offer
/// with the given fields replaced by the non-null parameter values.
@pragma('vm:prefer-inline') @override $Res call({Object? id = null,Object? discountType = null,Object? discountValue = null,Object? priceSnapshot = null,Object? discountedPrice = null,Object? startsAt = null,Object? endsAt = null,Object? isActive = null,Object? seller = freezed,Object? product = freezed,Object? createdAt = freezed,}) {
  return _then(_self.copyWith(
id: null == id ? _self.id : id // ignore: cast_nullable_to_non_nullable
as int,discountType: null == discountType ? _self.discountType : discountType // ignore: cast_nullable_to_non_nullable
as String,discountValue: null == discountValue ? _self.discountValue : discountValue // ignore: cast_nullable_to_non_nullable
as double,priceSnapshot: null == priceSnapshot ? _self.priceSnapshot : priceSnapshot // ignore: cast_nullable_to_non_nullable
as double,discountedPrice: null == discountedPrice ? _self.discountedPrice : discountedPrice // ignore: cast_nullable_to_non_nullable
as int,startsAt: null == startsAt ? _self.startsAt : startsAt // ignore: cast_nullable_to_non_nullable
as DateTime,endsAt: null == endsAt ? _self.endsAt : endsAt // ignore: cast_nullable_to_non_nullable
as DateTime,isActive: null == isActive ? _self.isActive : isActive // ignore: cast_nullable_to_non_nullable
as bool,seller: freezed == seller ? _self.seller : seller // ignore: cast_nullable_to_non_nullable
as SellerSummary?,product: freezed == product ? _self.product : product // ignore: cast_nullable_to_non_nullable
as ProductSummary?,createdAt: freezed == createdAt ? _self.createdAt : createdAt // ignore: cast_nullable_to_non_nullable
as DateTime?,
  ));
}
/// Create a copy of Offer
/// with the given fields replaced by the non-null parameter values.
@override
@pragma('vm:prefer-inline')
$SellerSummaryCopyWith<$Res>? get seller {
    if (_self.seller == null) {
    return null;
  }

  return $SellerSummaryCopyWith<$Res>(_self.seller!, (value) {
    return _then(_self.copyWith(seller: value));
  });
}/// Create a copy of Offer
/// with the given fields replaced by the non-null parameter values.
@override
@pragma('vm:prefer-inline')
$ProductSummaryCopyWith<$Res>? get product {
    if (_self.product == null) {
    return null;
  }

  return $ProductSummaryCopyWith<$Res>(_self.product!, (value) {
    return _then(_self.copyWith(product: value));
  });
}
}


/// Adds pattern-matching-related methods to [Offer].
extension OfferPatterns on Offer {
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

@optionalTypeArgs TResult maybeMap<TResult extends Object?>(TResult Function( _Offer value)?  $default,{required TResult orElse(),}){
final _that = this;
switch (_that) {
case _Offer() when $default != null:
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

@optionalTypeArgs TResult map<TResult extends Object?>(TResult Function( _Offer value)  $default,){
final _that = this;
switch (_that) {
case _Offer():
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

@optionalTypeArgs TResult? mapOrNull<TResult extends Object?>(TResult? Function( _Offer value)?  $default,){
final _that = this;
switch (_that) {
case _Offer() when $default != null:
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

@optionalTypeArgs TResult maybeWhen<TResult extends Object?>(TResult Function( int id, @JsonKey(name: 'discount_type')  String discountType, @JsonKey(name: 'discount_value')  double discountValue, @JsonKey(name: 'price_snapshot')  double priceSnapshot, @JsonKey(name: 'discounted_price')  int discountedPrice, @JsonKey(name: 'starts_at')  DateTime startsAt, @JsonKey(name: 'ends_at')  DateTime endsAt, @JsonKey(name: 'is_active')  bool isActive,  SellerSummary? seller,  ProductSummary? product, @JsonKey(name: 'created_at')  DateTime? createdAt)?  $default,{required TResult orElse(),}) {final _that = this;
switch (_that) {
case _Offer() when $default != null:
return $default(_that.id,_that.discountType,_that.discountValue,_that.priceSnapshot,_that.discountedPrice,_that.startsAt,_that.endsAt,_that.isActive,_that.seller,_that.product,_that.createdAt);case _:
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

@optionalTypeArgs TResult when<TResult extends Object?>(TResult Function( int id, @JsonKey(name: 'discount_type')  String discountType, @JsonKey(name: 'discount_value')  double discountValue, @JsonKey(name: 'price_snapshot')  double priceSnapshot, @JsonKey(name: 'discounted_price')  int discountedPrice, @JsonKey(name: 'starts_at')  DateTime startsAt, @JsonKey(name: 'ends_at')  DateTime endsAt, @JsonKey(name: 'is_active')  bool isActive,  SellerSummary? seller,  ProductSummary? product, @JsonKey(name: 'created_at')  DateTime? createdAt)  $default,) {final _that = this;
switch (_that) {
case _Offer():
return $default(_that.id,_that.discountType,_that.discountValue,_that.priceSnapshot,_that.discountedPrice,_that.startsAt,_that.endsAt,_that.isActive,_that.seller,_that.product,_that.createdAt);case _:
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

@optionalTypeArgs TResult? whenOrNull<TResult extends Object?>(TResult? Function( int id, @JsonKey(name: 'discount_type')  String discountType, @JsonKey(name: 'discount_value')  double discountValue, @JsonKey(name: 'price_snapshot')  double priceSnapshot, @JsonKey(name: 'discounted_price')  int discountedPrice, @JsonKey(name: 'starts_at')  DateTime startsAt, @JsonKey(name: 'ends_at')  DateTime endsAt, @JsonKey(name: 'is_active')  bool isActive,  SellerSummary? seller,  ProductSummary? product, @JsonKey(name: 'created_at')  DateTime? createdAt)?  $default,) {final _that = this;
switch (_that) {
case _Offer() when $default != null:
return $default(_that.id,_that.discountType,_that.discountValue,_that.priceSnapshot,_that.discountedPrice,_that.startsAt,_that.endsAt,_that.isActive,_that.seller,_that.product,_that.createdAt);case _:
  return null;

}
}

}

/// @nodoc
@JsonSerializable()

class _Offer implements Offer {
  const _Offer({required this.id, @JsonKey(name: 'discount_type') required this.discountType, @JsonKey(name: 'discount_value') required this.discountValue, @JsonKey(name: 'price_snapshot') required this.priceSnapshot, @JsonKey(name: 'discounted_price') required this.discountedPrice, @JsonKey(name: 'starts_at') required this.startsAt, @JsonKey(name: 'ends_at') required this.endsAt, @JsonKey(name: 'is_active') this.isActive = true, this.seller, this.product, @JsonKey(name: 'created_at') this.createdAt});
  factory _Offer.fromJson(Map<String, dynamic> json) => _$OfferFromJson(json);

@override final  int id;
@override@JsonKey(name: 'discount_type') final  String discountType;
@override@JsonKey(name: 'discount_value') final  double discountValue;
@override@JsonKey(name: 'price_snapshot') final  double priceSnapshot;
@override@JsonKey(name: 'discounted_price') final  int discountedPrice;
@override@JsonKey(name: 'starts_at') final  DateTime startsAt;
@override@JsonKey(name: 'ends_at') final  DateTime endsAt;
@override@JsonKey(name: 'is_active') final  bool isActive;
@override final  SellerSummary? seller;
@override final  ProductSummary? product;
@override@JsonKey(name: 'created_at') final  DateTime? createdAt;

/// Create a copy of Offer
/// with the given fields replaced by the non-null parameter values.
@override @JsonKey(includeFromJson: false, includeToJson: false)
@pragma('vm:prefer-inline')
_$OfferCopyWith<_Offer> get copyWith => __$OfferCopyWithImpl<_Offer>(this, _$identity);

@override
Map<String, dynamic> toJson() {
  return _$OfferToJson(this, );
}

@override
bool operator ==(Object other) {
  return identical(this, other) || (other.runtimeType == runtimeType&&other is _Offer&&(identical(other.id, id) || other.id == id)&&(identical(other.discountType, discountType) || other.discountType == discountType)&&(identical(other.discountValue, discountValue) || other.discountValue == discountValue)&&(identical(other.priceSnapshot, priceSnapshot) || other.priceSnapshot == priceSnapshot)&&(identical(other.discountedPrice, discountedPrice) || other.discountedPrice == discountedPrice)&&(identical(other.startsAt, startsAt) || other.startsAt == startsAt)&&(identical(other.endsAt, endsAt) || other.endsAt == endsAt)&&(identical(other.isActive, isActive) || other.isActive == isActive)&&(identical(other.seller, seller) || other.seller == seller)&&(identical(other.product, product) || other.product == product)&&(identical(other.createdAt, createdAt) || other.createdAt == createdAt));
}

@JsonKey(includeFromJson: false, includeToJson: false)
@override
int get hashCode => Object.hash(runtimeType,id,discountType,discountValue,priceSnapshot,discountedPrice,startsAt,endsAt,isActive,seller,product,createdAt);

@override
String toString() {
  return 'Offer(id: $id, discountType: $discountType, discountValue: $discountValue, priceSnapshot: $priceSnapshot, discountedPrice: $discountedPrice, startsAt: $startsAt, endsAt: $endsAt, isActive: $isActive, seller: $seller, product: $product, createdAt: $createdAt)';
}


}

/// @nodoc
abstract mixin class _$OfferCopyWith<$Res> implements $OfferCopyWith<$Res> {
  factory _$OfferCopyWith(_Offer value, $Res Function(_Offer) _then) = __$OfferCopyWithImpl;
@override @useResult
$Res call({
 int id,@JsonKey(name: 'discount_type') String discountType,@JsonKey(name: 'discount_value') double discountValue,@JsonKey(name: 'price_snapshot') double priceSnapshot,@JsonKey(name: 'discounted_price') int discountedPrice,@JsonKey(name: 'starts_at') DateTime startsAt,@JsonKey(name: 'ends_at') DateTime endsAt,@JsonKey(name: 'is_active') bool isActive, SellerSummary? seller, ProductSummary? product,@JsonKey(name: 'created_at') DateTime? createdAt
});


@override $SellerSummaryCopyWith<$Res>? get seller;@override $ProductSummaryCopyWith<$Res>? get product;

}
/// @nodoc
class __$OfferCopyWithImpl<$Res>
    implements _$OfferCopyWith<$Res> {
  __$OfferCopyWithImpl(this._self, this._then);

  final _Offer _self;
  final $Res Function(_Offer) _then;

/// Create a copy of Offer
/// with the given fields replaced by the non-null parameter values.
@override @pragma('vm:prefer-inline') $Res call({Object? id = null,Object? discountType = null,Object? discountValue = null,Object? priceSnapshot = null,Object? discountedPrice = null,Object? startsAt = null,Object? endsAt = null,Object? isActive = null,Object? seller = freezed,Object? product = freezed,Object? createdAt = freezed,}) {
  return _then(_Offer(
id: null == id ? _self.id : id // ignore: cast_nullable_to_non_nullable
as int,discountType: null == discountType ? _self.discountType : discountType // ignore: cast_nullable_to_non_nullable
as String,discountValue: null == discountValue ? _self.discountValue : discountValue // ignore: cast_nullable_to_non_nullable
as double,priceSnapshot: null == priceSnapshot ? _self.priceSnapshot : priceSnapshot // ignore: cast_nullable_to_non_nullable
as double,discountedPrice: null == discountedPrice ? _self.discountedPrice : discountedPrice // ignore: cast_nullable_to_non_nullable
as int,startsAt: null == startsAt ? _self.startsAt : startsAt // ignore: cast_nullable_to_non_nullable
as DateTime,endsAt: null == endsAt ? _self.endsAt : endsAt // ignore: cast_nullable_to_non_nullable
as DateTime,isActive: null == isActive ? _self.isActive : isActive // ignore: cast_nullable_to_non_nullable
as bool,seller: freezed == seller ? _self.seller : seller // ignore: cast_nullable_to_non_nullable
as SellerSummary?,product: freezed == product ? _self.product : product // ignore: cast_nullable_to_non_nullable
as ProductSummary?,createdAt: freezed == createdAt ? _self.createdAt : createdAt // ignore: cast_nullable_to_non_nullable
as DateTime?,
  ));
}

/// Create a copy of Offer
/// with the given fields replaced by the non-null parameter values.
@override
@pragma('vm:prefer-inline')
$SellerSummaryCopyWith<$Res>? get seller {
    if (_self.seller == null) {
    return null;
  }

  return $SellerSummaryCopyWith<$Res>(_self.seller!, (value) {
    return _then(_self.copyWith(seller: value));
  });
}/// Create a copy of Offer
/// with the given fields replaced by the non-null parameter values.
@override
@pragma('vm:prefer-inline')
$ProductSummaryCopyWith<$Res>? get product {
    if (_self.product == null) {
    return null;
  }

  return $ProductSummaryCopyWith<$Res>(_self.product!, (value) {
    return _then(_self.copyWith(product: value));
  });
}
}

// dart format on
