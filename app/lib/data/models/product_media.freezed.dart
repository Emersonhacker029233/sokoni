// GENERATED CODE - DO NOT MODIFY BY HAND
// coverage:ignore-file
// ignore_for_file: type=lint
// ignore_for_file: unused_element, deprecated_member_use, deprecated_member_use_from_same_package, use_function_type_syntax_for_parameters, unnecessary_const, avoid_init_to_null, invalid_override_different_default_values_named, prefer_expression_function_bodies, annotate_overrides, invalid_annotation_target, unnecessary_question_mark

part of 'product_media.dart';

// **************************************************************************
// FreezedGenerator
// **************************************************************************

// dart format off
T _$identity<T>(T value) => value;

/// @nodoc
mixin _$ProductMediaItem {

 int get id; String get type; String get path;@JsonKey(name: 'thumb_path') String? get thumbPath; int? get duration; int get sort;
/// Create a copy of ProductMediaItem
/// with the given fields replaced by the non-null parameter values.
@JsonKey(includeFromJson: false, includeToJson: false)
@pragma('vm:prefer-inline')
$ProductMediaItemCopyWith<ProductMediaItem> get copyWith => _$ProductMediaItemCopyWithImpl<ProductMediaItem>(this as ProductMediaItem, _$identity);

  /// Serializes this ProductMediaItem to a JSON map.
  Map<String, dynamic> toJson();


@override
bool operator ==(Object other) {
  return identical(this, other) || (other.runtimeType == runtimeType&&other is ProductMediaItem&&(identical(other.id, id) || other.id == id)&&(identical(other.type, type) || other.type == type)&&(identical(other.path, path) || other.path == path)&&(identical(other.thumbPath, thumbPath) || other.thumbPath == thumbPath)&&(identical(other.duration, duration) || other.duration == duration)&&(identical(other.sort, sort) || other.sort == sort));
}

@JsonKey(includeFromJson: false, includeToJson: false)
@override
int get hashCode => Object.hash(runtimeType,id,type,path,thumbPath,duration,sort);

@override
String toString() {
  return 'ProductMediaItem(id: $id, type: $type, path: $path, thumbPath: $thumbPath, duration: $duration, sort: $sort)';
}


}

/// @nodoc
abstract mixin class $ProductMediaItemCopyWith<$Res>  {
  factory $ProductMediaItemCopyWith(ProductMediaItem value, $Res Function(ProductMediaItem) _then) = _$ProductMediaItemCopyWithImpl;
@useResult
$Res call({
 int id, String type, String path,@JsonKey(name: 'thumb_path') String? thumbPath, int? duration, int sort
});




}
/// @nodoc
class _$ProductMediaItemCopyWithImpl<$Res>
    implements $ProductMediaItemCopyWith<$Res> {
  _$ProductMediaItemCopyWithImpl(this._self, this._then);

  final ProductMediaItem _self;
  final $Res Function(ProductMediaItem) _then;

/// Create a copy of ProductMediaItem
/// with the given fields replaced by the non-null parameter values.
@pragma('vm:prefer-inline') @override $Res call({Object? id = null,Object? type = null,Object? path = null,Object? thumbPath = freezed,Object? duration = freezed,Object? sort = null,}) {
  return _then(_self.copyWith(
id: null == id ? _self.id : id // ignore: cast_nullable_to_non_nullable
as int,type: null == type ? _self.type : type // ignore: cast_nullable_to_non_nullable
as String,path: null == path ? _self.path : path // ignore: cast_nullable_to_non_nullable
as String,thumbPath: freezed == thumbPath ? _self.thumbPath : thumbPath // ignore: cast_nullable_to_non_nullable
as String?,duration: freezed == duration ? _self.duration : duration // ignore: cast_nullable_to_non_nullable
as int?,sort: null == sort ? _self.sort : sort // ignore: cast_nullable_to_non_nullable
as int,
  ));
}

}


/// Adds pattern-matching-related methods to [ProductMediaItem].
extension ProductMediaItemPatterns on ProductMediaItem {
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

@optionalTypeArgs TResult maybeMap<TResult extends Object?>(TResult Function( _ProductMediaItem value)?  $default,{required TResult orElse(),}){
final _that = this;
switch (_that) {
case _ProductMediaItem() when $default != null:
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

@optionalTypeArgs TResult map<TResult extends Object?>(TResult Function( _ProductMediaItem value)  $default,){
final _that = this;
switch (_that) {
case _ProductMediaItem():
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

@optionalTypeArgs TResult? mapOrNull<TResult extends Object?>(TResult? Function( _ProductMediaItem value)?  $default,){
final _that = this;
switch (_that) {
case _ProductMediaItem() when $default != null:
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

@optionalTypeArgs TResult maybeWhen<TResult extends Object?>(TResult Function( int id,  String type,  String path, @JsonKey(name: 'thumb_path')  String? thumbPath,  int? duration,  int sort)?  $default,{required TResult orElse(),}) {final _that = this;
switch (_that) {
case _ProductMediaItem() when $default != null:
return $default(_that.id,_that.type,_that.path,_that.thumbPath,_that.duration,_that.sort);case _:
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

@optionalTypeArgs TResult when<TResult extends Object?>(TResult Function( int id,  String type,  String path, @JsonKey(name: 'thumb_path')  String? thumbPath,  int? duration,  int sort)  $default,) {final _that = this;
switch (_that) {
case _ProductMediaItem():
return $default(_that.id,_that.type,_that.path,_that.thumbPath,_that.duration,_that.sort);case _:
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

@optionalTypeArgs TResult? whenOrNull<TResult extends Object?>(TResult? Function( int id,  String type,  String path, @JsonKey(name: 'thumb_path')  String? thumbPath,  int? duration,  int sort)?  $default,) {final _that = this;
switch (_that) {
case _ProductMediaItem() when $default != null:
return $default(_that.id,_that.type,_that.path,_that.thumbPath,_that.duration,_that.sort);case _:
  return null;

}
}

}

/// @nodoc
@JsonSerializable()

class _ProductMediaItem implements ProductMediaItem {
  const _ProductMediaItem({required this.id, required this.type, required this.path, @JsonKey(name: 'thumb_path') this.thumbPath, this.duration, this.sort = 0});
  factory _ProductMediaItem.fromJson(Map<String, dynamic> json) => _$ProductMediaItemFromJson(json);

@override final  int id;
@override final  String type;
@override final  String path;
@override@JsonKey(name: 'thumb_path') final  String? thumbPath;
@override final  int? duration;
@override@JsonKey() final  int sort;

/// Create a copy of ProductMediaItem
/// with the given fields replaced by the non-null parameter values.
@override @JsonKey(includeFromJson: false, includeToJson: false)
@pragma('vm:prefer-inline')
_$ProductMediaItemCopyWith<_ProductMediaItem> get copyWith => __$ProductMediaItemCopyWithImpl<_ProductMediaItem>(this, _$identity);

@override
Map<String, dynamic> toJson() {
  return _$ProductMediaItemToJson(this, );
}

@override
bool operator ==(Object other) {
  return identical(this, other) || (other.runtimeType == runtimeType&&other is _ProductMediaItem&&(identical(other.id, id) || other.id == id)&&(identical(other.type, type) || other.type == type)&&(identical(other.path, path) || other.path == path)&&(identical(other.thumbPath, thumbPath) || other.thumbPath == thumbPath)&&(identical(other.duration, duration) || other.duration == duration)&&(identical(other.sort, sort) || other.sort == sort));
}

@JsonKey(includeFromJson: false, includeToJson: false)
@override
int get hashCode => Object.hash(runtimeType,id,type,path,thumbPath,duration,sort);

@override
String toString() {
  return 'ProductMediaItem(id: $id, type: $type, path: $path, thumbPath: $thumbPath, duration: $duration, sort: $sort)';
}


}

/// @nodoc
abstract mixin class _$ProductMediaItemCopyWith<$Res> implements $ProductMediaItemCopyWith<$Res> {
  factory _$ProductMediaItemCopyWith(_ProductMediaItem value, $Res Function(_ProductMediaItem) _then) = __$ProductMediaItemCopyWithImpl;
@override @useResult
$Res call({
 int id, String type, String path,@JsonKey(name: 'thumb_path') String? thumbPath, int? duration, int sort
});




}
/// @nodoc
class __$ProductMediaItemCopyWithImpl<$Res>
    implements _$ProductMediaItemCopyWith<$Res> {
  __$ProductMediaItemCopyWithImpl(this._self, this._then);

  final _ProductMediaItem _self;
  final $Res Function(_ProductMediaItem) _then;

/// Create a copy of ProductMediaItem
/// with the given fields replaced by the non-null parameter values.
@override @pragma('vm:prefer-inline') $Res call({Object? id = null,Object? type = null,Object? path = null,Object? thumbPath = freezed,Object? duration = freezed,Object? sort = null,}) {
  return _then(_ProductMediaItem(
id: null == id ? _self.id : id // ignore: cast_nullable_to_non_nullable
as int,type: null == type ? _self.type : type // ignore: cast_nullable_to_non_nullable
as String,path: null == path ? _self.path : path // ignore: cast_nullable_to_non_nullable
as String,thumbPath: freezed == thumbPath ? _self.thumbPath : thumbPath // ignore: cast_nullable_to_non_nullable
as String?,duration: freezed == duration ? _self.duration : duration // ignore: cast_nullable_to_non_nullable
as int?,sort: null == sort ? _self.sort : sort // ignore: cast_nullable_to_non_nullable
as int,
  ));
}


}

// dart format on
