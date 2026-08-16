// GENERATED CODE - DO NOT MODIFY BY HAND
// coverage:ignore-file
// ignore_for_file: type=lint
// ignore_for_file: unused_element, deprecated_member_use, deprecated_member_use_from_same_package, use_function_type_syntax_for_parameters, unnecessary_const, avoid_init_to_null, invalid_override_different_default_values_named, prefer_expression_function_bodies, annotate_overrides, invalid_annotation_target, unnecessary_question_mark

part of 'product_summary.dart';

// **************************************************************************
// FreezedGenerator
// **************************************************************************

// dart format off
T _$identity<T>(T value) => value;

/// @nodoc
mixin _$ProductSummary {

 int get id; String get title; int get price; String get currency;@JsonKey(name: 'cover_image_url') String? get coverImageUrl;
/// Create a copy of ProductSummary
/// with the given fields replaced by the non-null parameter values.
@JsonKey(includeFromJson: false, includeToJson: false)
@pragma('vm:prefer-inline')
$ProductSummaryCopyWith<ProductSummary> get copyWith => _$ProductSummaryCopyWithImpl<ProductSummary>(this as ProductSummary, _$identity);

  /// Serializes this ProductSummary to a JSON map.
  Map<String, dynamic> toJson();


@override
bool operator ==(Object other) {
  return identical(this, other) || (other.runtimeType == runtimeType&&other is ProductSummary&&(identical(other.id, id) || other.id == id)&&(identical(other.title, title) || other.title == title)&&(identical(other.price, price) || other.price == price)&&(identical(other.currency, currency) || other.currency == currency)&&(identical(other.coverImageUrl, coverImageUrl) || other.coverImageUrl == coverImageUrl));
}

@JsonKey(includeFromJson: false, includeToJson: false)
@override
int get hashCode => Object.hash(runtimeType,id,title,price,currency,coverImageUrl);

@override
String toString() {
  return 'ProductSummary(id: $id, title: $title, price: $price, currency: $currency, coverImageUrl: $coverImageUrl)';
}


}

/// @nodoc
abstract mixin class $ProductSummaryCopyWith<$Res>  {
  factory $ProductSummaryCopyWith(ProductSummary value, $Res Function(ProductSummary) _then) = _$ProductSummaryCopyWithImpl;
@useResult
$Res call({
 int id, String title, int price, String currency,@JsonKey(name: 'cover_image_url') String? coverImageUrl
});




}
/// @nodoc
class _$ProductSummaryCopyWithImpl<$Res>
    implements $ProductSummaryCopyWith<$Res> {
  _$ProductSummaryCopyWithImpl(this._self, this._then);

  final ProductSummary _self;
  final $Res Function(ProductSummary) _then;

/// Create a copy of ProductSummary
/// with the given fields replaced by the non-null parameter values.
@pragma('vm:prefer-inline') @override $Res call({Object? id = null,Object? title = null,Object? price = null,Object? currency = null,Object? coverImageUrl = freezed,}) {
  return _then(_self.copyWith(
id: null == id ? _self.id : id // ignore: cast_nullable_to_non_nullable
as int,title: null == title ? _self.title : title // ignore: cast_nullable_to_non_nullable
as String,price: null == price ? _self.price : price // ignore: cast_nullable_to_non_nullable
as int,currency: null == currency ? _self.currency : currency // ignore: cast_nullable_to_non_nullable
as String,coverImageUrl: freezed == coverImageUrl ? _self.coverImageUrl : coverImageUrl // ignore: cast_nullable_to_non_nullable
as String?,
  ));
}

}


/// Adds pattern-matching-related methods to [ProductSummary].
extension ProductSummaryPatterns on ProductSummary {
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

@optionalTypeArgs TResult maybeMap<TResult extends Object?>(TResult Function( _ProductSummary value)?  $default,{required TResult orElse(),}){
final _that = this;
switch (_that) {
case _ProductSummary() when $default != null:
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

@optionalTypeArgs TResult map<TResult extends Object?>(TResult Function( _ProductSummary value)  $default,){
final _that = this;
switch (_that) {
case _ProductSummary():
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

@optionalTypeArgs TResult? mapOrNull<TResult extends Object?>(TResult? Function( _ProductSummary value)?  $default,){
final _that = this;
switch (_that) {
case _ProductSummary() when $default != null:
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

@optionalTypeArgs TResult maybeWhen<TResult extends Object?>(TResult Function( int id,  String title,  int price,  String currency, @JsonKey(name: 'cover_image_url')  String? coverImageUrl)?  $default,{required TResult orElse(),}) {final _that = this;
switch (_that) {
case _ProductSummary() when $default != null:
return $default(_that.id,_that.title,_that.price,_that.currency,_that.coverImageUrl);case _:
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

@optionalTypeArgs TResult when<TResult extends Object?>(TResult Function( int id,  String title,  int price,  String currency, @JsonKey(name: 'cover_image_url')  String? coverImageUrl)  $default,) {final _that = this;
switch (_that) {
case _ProductSummary():
return $default(_that.id,_that.title,_that.price,_that.currency,_that.coverImageUrl);case _:
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

@optionalTypeArgs TResult? whenOrNull<TResult extends Object?>(TResult? Function( int id,  String title,  int price,  String currency, @JsonKey(name: 'cover_image_url')  String? coverImageUrl)?  $default,) {final _that = this;
switch (_that) {
case _ProductSummary() when $default != null:
return $default(_that.id,_that.title,_that.price,_that.currency,_that.coverImageUrl);case _:
  return null;

}
}

}

/// @nodoc
@JsonSerializable()

class _ProductSummary implements ProductSummary {
  const _ProductSummary({required this.id, required this.title, required this.price, this.currency = 'TZS', @JsonKey(name: 'cover_image_url') this.coverImageUrl});
  factory _ProductSummary.fromJson(Map<String, dynamic> json) => _$ProductSummaryFromJson(json);

@override final  int id;
@override final  String title;
@override final  int price;
@override@JsonKey() final  String currency;
@override@JsonKey(name: 'cover_image_url') final  String? coverImageUrl;

/// Create a copy of ProductSummary
/// with the given fields replaced by the non-null parameter values.
@override @JsonKey(includeFromJson: false, includeToJson: false)
@pragma('vm:prefer-inline')
_$ProductSummaryCopyWith<_ProductSummary> get copyWith => __$ProductSummaryCopyWithImpl<_ProductSummary>(this, _$identity);

@override
Map<String, dynamic> toJson() {
  return _$ProductSummaryToJson(this, );
}

@override
bool operator ==(Object other) {
  return identical(this, other) || (other.runtimeType == runtimeType&&other is _ProductSummary&&(identical(other.id, id) || other.id == id)&&(identical(other.title, title) || other.title == title)&&(identical(other.price, price) || other.price == price)&&(identical(other.currency, currency) || other.currency == currency)&&(identical(other.coverImageUrl, coverImageUrl) || other.coverImageUrl == coverImageUrl));
}

@JsonKey(includeFromJson: false, includeToJson: false)
@override
int get hashCode => Object.hash(runtimeType,id,title,price,currency,coverImageUrl);

@override
String toString() {
  return 'ProductSummary(id: $id, title: $title, price: $price, currency: $currency, coverImageUrl: $coverImageUrl)';
}


}

/// @nodoc
abstract mixin class _$ProductSummaryCopyWith<$Res> implements $ProductSummaryCopyWith<$Res> {
  factory _$ProductSummaryCopyWith(_ProductSummary value, $Res Function(_ProductSummary) _then) = __$ProductSummaryCopyWithImpl;
@override @useResult
$Res call({
 int id, String title, int price, String currency,@JsonKey(name: 'cover_image_url') String? coverImageUrl
});




}
/// @nodoc
class __$ProductSummaryCopyWithImpl<$Res>
    implements _$ProductSummaryCopyWith<$Res> {
  __$ProductSummaryCopyWithImpl(this._self, this._then);

  final _ProductSummary _self;
  final $Res Function(_ProductSummary) _then;

/// Create a copy of ProductSummary
/// with the given fields replaced by the non-null parameter values.
@override @pragma('vm:prefer-inline') $Res call({Object? id = null,Object? title = null,Object? price = null,Object? currency = null,Object? coverImageUrl = freezed,}) {
  return _then(_ProductSummary(
id: null == id ? _self.id : id // ignore: cast_nullable_to_non_nullable
as int,title: null == title ? _self.title : title // ignore: cast_nullable_to_non_nullable
as String,price: null == price ? _self.price : price // ignore: cast_nullable_to_non_nullable
as int,currency: null == currency ? _self.currency : currency // ignore: cast_nullable_to_non_nullable
as String,coverImageUrl: freezed == coverImageUrl ? _self.coverImageUrl : coverImageUrl // ignore: cast_nullable_to_non_nullable
as String?,
  ));
}


}

// dart format on
