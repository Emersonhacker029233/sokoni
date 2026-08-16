// GENERATED CODE - DO NOT MODIFY BY HAND
// coverage:ignore-file
// ignore_for_file: type=lint
// ignore_for_file: unused_element, deprecated_member_use, deprecated_member_use_from_same_package, use_function_type_syntax_for_parameters, unnecessary_const, avoid_init_to_null, invalid_override_different_default_values_named, prefer_expression_function_bodies, annotate_overrides, invalid_annotation_target, unnecessary_question_mark

part of 'social_update.dart';

// **************************************************************************
// FreezedGenerator
// **************************************************************************

// dart format off
T _$identity<T>(T value) => value;

/// @nodoc
mixin _$SocialUpdate {

 int get id; String get type;@JsonKey(name: 'media_path') String get mediaPath;@JsonKey(name: 'thumb_path') String? get thumbPath; String? get caption; SellerSummary? get seller; ProductSummary? get product;@JsonKey(name: 'expires_at') DateTime get expiresAt;@JsonKey(name: 'created_at') DateTime? get createdAt;
/// Create a copy of SocialUpdate
/// with the given fields replaced by the non-null parameter values.
@JsonKey(includeFromJson: false, includeToJson: false)
@pragma('vm:prefer-inline')
$SocialUpdateCopyWith<SocialUpdate> get copyWith => _$SocialUpdateCopyWithImpl<SocialUpdate>(this as SocialUpdate, _$identity);

  /// Serializes this SocialUpdate to a JSON map.
  Map<String, dynamic> toJson();


@override
bool operator ==(Object other) {
  return identical(this, other) || (other.runtimeType == runtimeType&&other is SocialUpdate&&(identical(other.id, id) || other.id == id)&&(identical(other.type, type) || other.type == type)&&(identical(other.mediaPath, mediaPath) || other.mediaPath == mediaPath)&&(identical(other.thumbPath, thumbPath) || other.thumbPath == thumbPath)&&(identical(other.caption, caption) || other.caption == caption)&&(identical(other.seller, seller) || other.seller == seller)&&(identical(other.product, product) || other.product == product)&&(identical(other.expiresAt, expiresAt) || other.expiresAt == expiresAt)&&(identical(other.createdAt, createdAt) || other.createdAt == createdAt));
}

@JsonKey(includeFromJson: false, includeToJson: false)
@override
int get hashCode => Object.hash(runtimeType,id,type,mediaPath,thumbPath,caption,seller,product,expiresAt,createdAt);

@override
String toString() {
  return 'SocialUpdate(id: $id, type: $type, mediaPath: $mediaPath, thumbPath: $thumbPath, caption: $caption, seller: $seller, product: $product, expiresAt: $expiresAt, createdAt: $createdAt)';
}


}

/// @nodoc
abstract mixin class $SocialUpdateCopyWith<$Res>  {
  factory $SocialUpdateCopyWith(SocialUpdate value, $Res Function(SocialUpdate) _then) = _$SocialUpdateCopyWithImpl;
@useResult
$Res call({
 int id, String type,@JsonKey(name: 'media_path') String mediaPath,@JsonKey(name: 'thumb_path') String? thumbPath, String? caption, SellerSummary? seller, ProductSummary? product,@JsonKey(name: 'expires_at') DateTime expiresAt,@JsonKey(name: 'created_at') DateTime? createdAt
});


$SellerSummaryCopyWith<$Res>? get seller;$ProductSummaryCopyWith<$Res>? get product;

}
/// @nodoc
class _$SocialUpdateCopyWithImpl<$Res>
    implements $SocialUpdateCopyWith<$Res> {
  _$SocialUpdateCopyWithImpl(this._self, this._then);

  final SocialUpdate _self;
  final $Res Function(SocialUpdate) _then;

/// Create a copy of SocialUpdate
/// with the given fields replaced by the non-null parameter values.
@pragma('vm:prefer-inline') @override $Res call({Object? id = null,Object? type = null,Object? mediaPath = null,Object? thumbPath = freezed,Object? caption = freezed,Object? seller = freezed,Object? product = freezed,Object? expiresAt = null,Object? createdAt = freezed,}) {
  return _then(_self.copyWith(
id: null == id ? _self.id : id // ignore: cast_nullable_to_non_nullable
as int,type: null == type ? _self.type : type // ignore: cast_nullable_to_non_nullable
as String,mediaPath: null == mediaPath ? _self.mediaPath : mediaPath // ignore: cast_nullable_to_non_nullable
as String,thumbPath: freezed == thumbPath ? _self.thumbPath : thumbPath // ignore: cast_nullable_to_non_nullable
as String?,caption: freezed == caption ? _self.caption : caption // ignore: cast_nullable_to_non_nullable
as String?,seller: freezed == seller ? _self.seller : seller // ignore: cast_nullable_to_non_nullable
as SellerSummary?,product: freezed == product ? _self.product : product // ignore: cast_nullable_to_non_nullable
as ProductSummary?,expiresAt: null == expiresAt ? _self.expiresAt : expiresAt // ignore: cast_nullable_to_non_nullable
as DateTime,createdAt: freezed == createdAt ? _self.createdAt : createdAt // ignore: cast_nullable_to_non_nullable
as DateTime?,
  ));
}
/// Create a copy of SocialUpdate
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
}/// Create a copy of SocialUpdate
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


/// Adds pattern-matching-related methods to [SocialUpdate].
extension SocialUpdatePatterns on SocialUpdate {
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

@optionalTypeArgs TResult maybeMap<TResult extends Object?>(TResult Function( _SocialUpdate value)?  $default,{required TResult orElse(),}){
final _that = this;
switch (_that) {
case _SocialUpdate() when $default != null:
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

@optionalTypeArgs TResult map<TResult extends Object?>(TResult Function( _SocialUpdate value)  $default,){
final _that = this;
switch (_that) {
case _SocialUpdate():
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

@optionalTypeArgs TResult? mapOrNull<TResult extends Object?>(TResult? Function( _SocialUpdate value)?  $default,){
final _that = this;
switch (_that) {
case _SocialUpdate() when $default != null:
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

@optionalTypeArgs TResult maybeWhen<TResult extends Object?>(TResult Function( int id,  String type, @JsonKey(name: 'media_path')  String mediaPath, @JsonKey(name: 'thumb_path')  String? thumbPath,  String? caption,  SellerSummary? seller,  ProductSummary? product, @JsonKey(name: 'expires_at')  DateTime expiresAt, @JsonKey(name: 'created_at')  DateTime? createdAt)?  $default,{required TResult orElse(),}) {final _that = this;
switch (_that) {
case _SocialUpdate() when $default != null:
return $default(_that.id,_that.type,_that.mediaPath,_that.thumbPath,_that.caption,_that.seller,_that.product,_that.expiresAt,_that.createdAt);case _:
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

@optionalTypeArgs TResult when<TResult extends Object?>(TResult Function( int id,  String type, @JsonKey(name: 'media_path')  String mediaPath, @JsonKey(name: 'thumb_path')  String? thumbPath,  String? caption,  SellerSummary? seller,  ProductSummary? product, @JsonKey(name: 'expires_at')  DateTime expiresAt, @JsonKey(name: 'created_at')  DateTime? createdAt)  $default,) {final _that = this;
switch (_that) {
case _SocialUpdate():
return $default(_that.id,_that.type,_that.mediaPath,_that.thumbPath,_that.caption,_that.seller,_that.product,_that.expiresAt,_that.createdAt);case _:
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

@optionalTypeArgs TResult? whenOrNull<TResult extends Object?>(TResult? Function( int id,  String type, @JsonKey(name: 'media_path')  String mediaPath, @JsonKey(name: 'thumb_path')  String? thumbPath,  String? caption,  SellerSummary? seller,  ProductSummary? product, @JsonKey(name: 'expires_at')  DateTime expiresAt, @JsonKey(name: 'created_at')  DateTime? createdAt)?  $default,) {final _that = this;
switch (_that) {
case _SocialUpdate() when $default != null:
return $default(_that.id,_that.type,_that.mediaPath,_that.thumbPath,_that.caption,_that.seller,_that.product,_that.expiresAt,_that.createdAt);case _:
  return null;

}
}

}

/// @nodoc
@JsonSerializable()

class _SocialUpdate implements SocialUpdate {
  const _SocialUpdate({required this.id, required this.type, @JsonKey(name: 'media_path') required this.mediaPath, @JsonKey(name: 'thumb_path') this.thumbPath, this.caption, this.seller, this.product, @JsonKey(name: 'expires_at') required this.expiresAt, @JsonKey(name: 'created_at') this.createdAt});
  factory _SocialUpdate.fromJson(Map<String, dynamic> json) => _$SocialUpdateFromJson(json);

@override final  int id;
@override final  String type;
@override@JsonKey(name: 'media_path') final  String mediaPath;
@override@JsonKey(name: 'thumb_path') final  String? thumbPath;
@override final  String? caption;
@override final  SellerSummary? seller;
@override final  ProductSummary? product;
@override@JsonKey(name: 'expires_at') final  DateTime expiresAt;
@override@JsonKey(name: 'created_at') final  DateTime? createdAt;

/// Create a copy of SocialUpdate
/// with the given fields replaced by the non-null parameter values.
@override @JsonKey(includeFromJson: false, includeToJson: false)
@pragma('vm:prefer-inline')
_$SocialUpdateCopyWith<_SocialUpdate> get copyWith => __$SocialUpdateCopyWithImpl<_SocialUpdate>(this, _$identity);

@override
Map<String, dynamic> toJson() {
  return _$SocialUpdateToJson(this, );
}

@override
bool operator ==(Object other) {
  return identical(this, other) || (other.runtimeType == runtimeType&&other is _SocialUpdate&&(identical(other.id, id) || other.id == id)&&(identical(other.type, type) || other.type == type)&&(identical(other.mediaPath, mediaPath) || other.mediaPath == mediaPath)&&(identical(other.thumbPath, thumbPath) || other.thumbPath == thumbPath)&&(identical(other.caption, caption) || other.caption == caption)&&(identical(other.seller, seller) || other.seller == seller)&&(identical(other.product, product) || other.product == product)&&(identical(other.expiresAt, expiresAt) || other.expiresAt == expiresAt)&&(identical(other.createdAt, createdAt) || other.createdAt == createdAt));
}

@JsonKey(includeFromJson: false, includeToJson: false)
@override
int get hashCode => Object.hash(runtimeType,id,type,mediaPath,thumbPath,caption,seller,product,expiresAt,createdAt);

@override
String toString() {
  return 'SocialUpdate(id: $id, type: $type, mediaPath: $mediaPath, thumbPath: $thumbPath, caption: $caption, seller: $seller, product: $product, expiresAt: $expiresAt, createdAt: $createdAt)';
}


}

/// @nodoc
abstract mixin class _$SocialUpdateCopyWith<$Res> implements $SocialUpdateCopyWith<$Res> {
  factory _$SocialUpdateCopyWith(_SocialUpdate value, $Res Function(_SocialUpdate) _then) = __$SocialUpdateCopyWithImpl;
@override @useResult
$Res call({
 int id, String type,@JsonKey(name: 'media_path') String mediaPath,@JsonKey(name: 'thumb_path') String? thumbPath, String? caption, SellerSummary? seller, ProductSummary? product,@JsonKey(name: 'expires_at') DateTime expiresAt,@JsonKey(name: 'created_at') DateTime? createdAt
});


@override $SellerSummaryCopyWith<$Res>? get seller;@override $ProductSummaryCopyWith<$Res>? get product;

}
/// @nodoc
class __$SocialUpdateCopyWithImpl<$Res>
    implements _$SocialUpdateCopyWith<$Res> {
  __$SocialUpdateCopyWithImpl(this._self, this._then);

  final _SocialUpdate _self;
  final $Res Function(_SocialUpdate) _then;

/// Create a copy of SocialUpdate
/// with the given fields replaced by the non-null parameter values.
@override @pragma('vm:prefer-inline') $Res call({Object? id = null,Object? type = null,Object? mediaPath = null,Object? thumbPath = freezed,Object? caption = freezed,Object? seller = freezed,Object? product = freezed,Object? expiresAt = null,Object? createdAt = freezed,}) {
  return _then(_SocialUpdate(
id: null == id ? _self.id : id // ignore: cast_nullable_to_non_nullable
as int,type: null == type ? _self.type : type // ignore: cast_nullable_to_non_nullable
as String,mediaPath: null == mediaPath ? _self.mediaPath : mediaPath // ignore: cast_nullable_to_non_nullable
as String,thumbPath: freezed == thumbPath ? _self.thumbPath : thumbPath // ignore: cast_nullable_to_non_nullable
as String?,caption: freezed == caption ? _self.caption : caption // ignore: cast_nullable_to_non_nullable
as String?,seller: freezed == seller ? _self.seller : seller // ignore: cast_nullable_to_non_nullable
as SellerSummary?,product: freezed == product ? _self.product : product // ignore: cast_nullable_to_non_nullable
as ProductSummary?,expiresAt: null == expiresAt ? _self.expiresAt : expiresAt // ignore: cast_nullable_to_non_nullable
as DateTime,createdAt: freezed == createdAt ? _self.createdAt : createdAt // ignore: cast_nullable_to_non_nullable
as DateTime?,
  ));
}

/// Create a copy of SocialUpdate
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
}/// Create a copy of SocialUpdate
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
