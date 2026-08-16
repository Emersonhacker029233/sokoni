// GENERATED CODE - DO NOT MODIFY BY HAND
// coverage:ignore-file
// ignore_for_file: type=lint
// ignore_for_file: unused_element, deprecated_member_use, deprecated_member_use_from_same_package, use_function_type_syntax_for_parameters, unnecessary_const, avoid_init_to_null, invalid_override_different_default_values_named, prefer_expression_function_bodies, annotate_overrides, invalid_annotation_target, unnecessary_question_mark

part of 'showcase.dart';

// **************************************************************************
// FreezedGenerator
// **************************************************************************

// dart format off
T _$identity<T>(T value) => value;

/// @nodoc
mixin _$Showcase {

 int get id;@JsonKey(name: 'video_path') String get videoPath;@JsonKey(name: 'thumb_path') String get thumbPath; String? get caption; int get duration; int get views; SellerSummary? get seller; ProductSummary? get product;@JsonKey(name: 'created_at') DateTime? get createdAt;
/// Create a copy of Showcase
/// with the given fields replaced by the non-null parameter values.
@JsonKey(includeFromJson: false, includeToJson: false)
@pragma('vm:prefer-inline')
$ShowcaseCopyWith<Showcase> get copyWith => _$ShowcaseCopyWithImpl<Showcase>(this as Showcase, _$identity);

  /// Serializes this Showcase to a JSON map.
  Map<String, dynamic> toJson();


@override
bool operator ==(Object other) {
  return identical(this, other) || (other.runtimeType == runtimeType&&other is Showcase&&(identical(other.id, id) || other.id == id)&&(identical(other.videoPath, videoPath) || other.videoPath == videoPath)&&(identical(other.thumbPath, thumbPath) || other.thumbPath == thumbPath)&&(identical(other.caption, caption) || other.caption == caption)&&(identical(other.duration, duration) || other.duration == duration)&&(identical(other.views, views) || other.views == views)&&(identical(other.seller, seller) || other.seller == seller)&&(identical(other.product, product) || other.product == product)&&(identical(other.createdAt, createdAt) || other.createdAt == createdAt));
}

@JsonKey(includeFromJson: false, includeToJson: false)
@override
int get hashCode => Object.hash(runtimeType,id,videoPath,thumbPath,caption,duration,views,seller,product,createdAt);

@override
String toString() {
  return 'Showcase(id: $id, videoPath: $videoPath, thumbPath: $thumbPath, caption: $caption, duration: $duration, views: $views, seller: $seller, product: $product, createdAt: $createdAt)';
}


}

/// @nodoc
abstract mixin class $ShowcaseCopyWith<$Res>  {
  factory $ShowcaseCopyWith(Showcase value, $Res Function(Showcase) _then) = _$ShowcaseCopyWithImpl;
@useResult
$Res call({
 int id,@JsonKey(name: 'video_path') String videoPath,@JsonKey(name: 'thumb_path') String thumbPath, String? caption, int duration, int views, SellerSummary? seller, ProductSummary? product,@JsonKey(name: 'created_at') DateTime? createdAt
});


$SellerSummaryCopyWith<$Res>? get seller;$ProductSummaryCopyWith<$Res>? get product;

}
/// @nodoc
class _$ShowcaseCopyWithImpl<$Res>
    implements $ShowcaseCopyWith<$Res> {
  _$ShowcaseCopyWithImpl(this._self, this._then);

  final Showcase _self;
  final $Res Function(Showcase) _then;

/// Create a copy of Showcase
/// with the given fields replaced by the non-null parameter values.
@pragma('vm:prefer-inline') @override $Res call({Object? id = null,Object? videoPath = null,Object? thumbPath = null,Object? caption = freezed,Object? duration = null,Object? views = null,Object? seller = freezed,Object? product = freezed,Object? createdAt = freezed,}) {
  return _then(_self.copyWith(
id: null == id ? _self.id : id // ignore: cast_nullable_to_non_nullable
as int,videoPath: null == videoPath ? _self.videoPath : videoPath // ignore: cast_nullable_to_non_nullable
as String,thumbPath: null == thumbPath ? _self.thumbPath : thumbPath // ignore: cast_nullable_to_non_nullable
as String,caption: freezed == caption ? _self.caption : caption // ignore: cast_nullable_to_non_nullable
as String?,duration: null == duration ? _self.duration : duration // ignore: cast_nullable_to_non_nullable
as int,views: null == views ? _self.views : views // ignore: cast_nullable_to_non_nullable
as int,seller: freezed == seller ? _self.seller : seller // ignore: cast_nullable_to_non_nullable
as SellerSummary?,product: freezed == product ? _self.product : product // ignore: cast_nullable_to_non_nullable
as ProductSummary?,createdAt: freezed == createdAt ? _self.createdAt : createdAt // ignore: cast_nullable_to_non_nullable
as DateTime?,
  ));
}
/// Create a copy of Showcase
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
}/// Create a copy of Showcase
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


/// Adds pattern-matching-related methods to [Showcase].
extension ShowcasePatterns on Showcase {
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

@optionalTypeArgs TResult maybeMap<TResult extends Object?>(TResult Function( _Showcase value)?  $default,{required TResult orElse(),}){
final _that = this;
switch (_that) {
case _Showcase() when $default != null:
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

@optionalTypeArgs TResult map<TResult extends Object?>(TResult Function( _Showcase value)  $default,){
final _that = this;
switch (_that) {
case _Showcase():
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

@optionalTypeArgs TResult? mapOrNull<TResult extends Object?>(TResult? Function( _Showcase value)?  $default,){
final _that = this;
switch (_that) {
case _Showcase() when $default != null:
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

@optionalTypeArgs TResult maybeWhen<TResult extends Object?>(TResult Function( int id, @JsonKey(name: 'video_path')  String videoPath, @JsonKey(name: 'thumb_path')  String thumbPath,  String? caption,  int duration,  int views,  SellerSummary? seller,  ProductSummary? product, @JsonKey(name: 'created_at')  DateTime? createdAt)?  $default,{required TResult orElse(),}) {final _that = this;
switch (_that) {
case _Showcase() when $default != null:
return $default(_that.id,_that.videoPath,_that.thumbPath,_that.caption,_that.duration,_that.views,_that.seller,_that.product,_that.createdAt);case _:
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

@optionalTypeArgs TResult when<TResult extends Object?>(TResult Function( int id, @JsonKey(name: 'video_path')  String videoPath, @JsonKey(name: 'thumb_path')  String thumbPath,  String? caption,  int duration,  int views,  SellerSummary? seller,  ProductSummary? product, @JsonKey(name: 'created_at')  DateTime? createdAt)  $default,) {final _that = this;
switch (_that) {
case _Showcase():
return $default(_that.id,_that.videoPath,_that.thumbPath,_that.caption,_that.duration,_that.views,_that.seller,_that.product,_that.createdAt);case _:
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

@optionalTypeArgs TResult? whenOrNull<TResult extends Object?>(TResult? Function( int id, @JsonKey(name: 'video_path')  String videoPath, @JsonKey(name: 'thumb_path')  String thumbPath,  String? caption,  int duration,  int views,  SellerSummary? seller,  ProductSummary? product, @JsonKey(name: 'created_at')  DateTime? createdAt)?  $default,) {final _that = this;
switch (_that) {
case _Showcase() when $default != null:
return $default(_that.id,_that.videoPath,_that.thumbPath,_that.caption,_that.duration,_that.views,_that.seller,_that.product,_that.createdAt);case _:
  return null;

}
}

}

/// @nodoc
@JsonSerializable()

class _Showcase implements Showcase {
  const _Showcase({required this.id, @JsonKey(name: 'video_path') required this.videoPath, @JsonKey(name: 'thumb_path') required this.thumbPath, this.caption, required this.duration, this.views = 0, this.seller, this.product, @JsonKey(name: 'created_at') this.createdAt});
  factory _Showcase.fromJson(Map<String, dynamic> json) => _$ShowcaseFromJson(json);

@override final  int id;
@override@JsonKey(name: 'video_path') final  String videoPath;
@override@JsonKey(name: 'thumb_path') final  String thumbPath;
@override final  String? caption;
@override final  int duration;
@override@JsonKey() final  int views;
@override final  SellerSummary? seller;
@override final  ProductSummary? product;
@override@JsonKey(name: 'created_at') final  DateTime? createdAt;

/// Create a copy of Showcase
/// with the given fields replaced by the non-null parameter values.
@override @JsonKey(includeFromJson: false, includeToJson: false)
@pragma('vm:prefer-inline')
_$ShowcaseCopyWith<_Showcase> get copyWith => __$ShowcaseCopyWithImpl<_Showcase>(this, _$identity);

@override
Map<String, dynamic> toJson() {
  return _$ShowcaseToJson(this, );
}

@override
bool operator ==(Object other) {
  return identical(this, other) || (other.runtimeType == runtimeType&&other is _Showcase&&(identical(other.id, id) || other.id == id)&&(identical(other.videoPath, videoPath) || other.videoPath == videoPath)&&(identical(other.thumbPath, thumbPath) || other.thumbPath == thumbPath)&&(identical(other.caption, caption) || other.caption == caption)&&(identical(other.duration, duration) || other.duration == duration)&&(identical(other.views, views) || other.views == views)&&(identical(other.seller, seller) || other.seller == seller)&&(identical(other.product, product) || other.product == product)&&(identical(other.createdAt, createdAt) || other.createdAt == createdAt));
}

@JsonKey(includeFromJson: false, includeToJson: false)
@override
int get hashCode => Object.hash(runtimeType,id,videoPath,thumbPath,caption,duration,views,seller,product,createdAt);

@override
String toString() {
  return 'Showcase(id: $id, videoPath: $videoPath, thumbPath: $thumbPath, caption: $caption, duration: $duration, views: $views, seller: $seller, product: $product, createdAt: $createdAt)';
}


}

/// @nodoc
abstract mixin class _$ShowcaseCopyWith<$Res> implements $ShowcaseCopyWith<$Res> {
  factory _$ShowcaseCopyWith(_Showcase value, $Res Function(_Showcase) _then) = __$ShowcaseCopyWithImpl;
@override @useResult
$Res call({
 int id,@JsonKey(name: 'video_path') String videoPath,@JsonKey(name: 'thumb_path') String thumbPath, String? caption, int duration, int views, SellerSummary? seller, ProductSummary? product,@JsonKey(name: 'created_at') DateTime? createdAt
});


@override $SellerSummaryCopyWith<$Res>? get seller;@override $ProductSummaryCopyWith<$Res>? get product;

}
/// @nodoc
class __$ShowcaseCopyWithImpl<$Res>
    implements _$ShowcaseCopyWith<$Res> {
  __$ShowcaseCopyWithImpl(this._self, this._then);

  final _Showcase _self;
  final $Res Function(_Showcase) _then;

/// Create a copy of Showcase
/// with the given fields replaced by the non-null parameter values.
@override @pragma('vm:prefer-inline') $Res call({Object? id = null,Object? videoPath = null,Object? thumbPath = null,Object? caption = freezed,Object? duration = null,Object? views = null,Object? seller = freezed,Object? product = freezed,Object? createdAt = freezed,}) {
  return _then(_Showcase(
id: null == id ? _self.id : id // ignore: cast_nullable_to_non_nullable
as int,videoPath: null == videoPath ? _self.videoPath : videoPath // ignore: cast_nullable_to_non_nullable
as String,thumbPath: null == thumbPath ? _self.thumbPath : thumbPath // ignore: cast_nullable_to_non_nullable
as String,caption: freezed == caption ? _self.caption : caption // ignore: cast_nullable_to_non_nullable
as String?,duration: null == duration ? _self.duration : duration // ignore: cast_nullable_to_non_nullable
as int,views: null == views ? _self.views : views // ignore: cast_nullable_to_non_nullable
as int,seller: freezed == seller ? _self.seller : seller // ignore: cast_nullable_to_non_nullable
as SellerSummary?,product: freezed == product ? _self.product : product // ignore: cast_nullable_to_non_nullable
as ProductSummary?,createdAt: freezed == createdAt ? _self.createdAt : createdAt // ignore: cast_nullable_to_non_nullable
as DateTime?,
  ));
}

/// Create a copy of Showcase
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
}/// Create a copy of Showcase
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
