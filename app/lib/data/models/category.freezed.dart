// GENERATED CODE - DO NOT MODIFY BY HAND
// coverage:ignore-file
// ignore_for_file: type=lint
// ignore_for_file: unused_element, deprecated_member_use, deprecated_member_use_from_same_package, use_function_type_syntax_for_parameters, unnecessary_const, avoid_init_to_null, invalid_override_different_default_values_named, prefer_expression_function_bodies, annotate_overrides, invalid_annotation_target, unnecessary_question_mark

part of 'category.dart';

// **************************************************************************
// FreezedGenerator
// **************************************************************************

// dart format off
T _$identity<T>(T value) => value;

/// @nodoc
mixin _$SokoniCategory {

 int get id;@JsonKey(name: 'parent_id') int? get parentId;@JsonKey(name: 'name_en') String get nameEn;@JsonKey(name: 'name_sw') String get nameSw; String? get icon;@JsonKey(name: 'sort_order') int get sortOrder;
/// Create a copy of SokoniCategory
/// with the given fields replaced by the non-null parameter values.
@JsonKey(includeFromJson: false, includeToJson: false)
@pragma('vm:prefer-inline')
$SokoniCategoryCopyWith<SokoniCategory> get copyWith => _$SokoniCategoryCopyWithImpl<SokoniCategory>(this as SokoniCategory, _$identity);

  /// Serializes this SokoniCategory to a JSON map.
  Map<String, dynamic> toJson();


@override
bool operator ==(Object other) {
  return identical(this, other) || (other.runtimeType == runtimeType&&other is SokoniCategory&&(identical(other.id, id) || other.id == id)&&(identical(other.parentId, parentId) || other.parentId == parentId)&&(identical(other.nameEn, nameEn) || other.nameEn == nameEn)&&(identical(other.nameSw, nameSw) || other.nameSw == nameSw)&&(identical(other.icon, icon) || other.icon == icon)&&(identical(other.sortOrder, sortOrder) || other.sortOrder == sortOrder));
}

@JsonKey(includeFromJson: false, includeToJson: false)
@override
int get hashCode => Object.hash(runtimeType,id,parentId,nameEn,nameSw,icon,sortOrder);

@override
String toString() {
  return 'SokoniCategory(id: $id, parentId: $parentId, nameEn: $nameEn, nameSw: $nameSw, icon: $icon, sortOrder: $sortOrder)';
}


}

/// @nodoc
abstract mixin class $SokoniCategoryCopyWith<$Res>  {
  factory $SokoniCategoryCopyWith(SokoniCategory value, $Res Function(SokoniCategory) _then) = _$SokoniCategoryCopyWithImpl;
@useResult
$Res call({
 int id,@JsonKey(name: 'parent_id') int? parentId,@JsonKey(name: 'name_en') String nameEn,@JsonKey(name: 'name_sw') String nameSw, String? icon,@JsonKey(name: 'sort_order') int sortOrder
});




}
/// @nodoc
class _$SokoniCategoryCopyWithImpl<$Res>
    implements $SokoniCategoryCopyWith<$Res> {
  _$SokoniCategoryCopyWithImpl(this._self, this._then);

  final SokoniCategory _self;
  final $Res Function(SokoniCategory) _then;

/// Create a copy of SokoniCategory
/// with the given fields replaced by the non-null parameter values.
@pragma('vm:prefer-inline') @override $Res call({Object? id = null,Object? parentId = freezed,Object? nameEn = null,Object? nameSw = null,Object? icon = freezed,Object? sortOrder = null,}) {
  return _then(_self.copyWith(
id: null == id ? _self.id : id // ignore: cast_nullable_to_non_nullable
as int,parentId: freezed == parentId ? _self.parentId : parentId // ignore: cast_nullable_to_non_nullable
as int?,nameEn: null == nameEn ? _self.nameEn : nameEn // ignore: cast_nullable_to_non_nullable
as String,nameSw: null == nameSw ? _self.nameSw : nameSw // ignore: cast_nullable_to_non_nullable
as String,icon: freezed == icon ? _self.icon : icon // ignore: cast_nullable_to_non_nullable
as String?,sortOrder: null == sortOrder ? _self.sortOrder : sortOrder // ignore: cast_nullable_to_non_nullable
as int,
  ));
}

}


/// Adds pattern-matching-related methods to [SokoniCategory].
extension SokoniCategoryPatterns on SokoniCategory {
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

@optionalTypeArgs TResult maybeMap<TResult extends Object?>(TResult Function( _SokoniCategory value)?  $default,{required TResult orElse(),}){
final _that = this;
switch (_that) {
case _SokoniCategory() when $default != null:
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

@optionalTypeArgs TResult map<TResult extends Object?>(TResult Function( _SokoniCategory value)  $default,){
final _that = this;
switch (_that) {
case _SokoniCategory():
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

@optionalTypeArgs TResult? mapOrNull<TResult extends Object?>(TResult? Function( _SokoniCategory value)?  $default,){
final _that = this;
switch (_that) {
case _SokoniCategory() when $default != null:
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

@optionalTypeArgs TResult maybeWhen<TResult extends Object?>(TResult Function( int id, @JsonKey(name: 'parent_id')  int? parentId, @JsonKey(name: 'name_en')  String nameEn, @JsonKey(name: 'name_sw')  String nameSw,  String? icon, @JsonKey(name: 'sort_order')  int sortOrder)?  $default,{required TResult orElse(),}) {final _that = this;
switch (_that) {
case _SokoniCategory() when $default != null:
return $default(_that.id,_that.parentId,_that.nameEn,_that.nameSw,_that.icon,_that.sortOrder);case _:
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

@optionalTypeArgs TResult when<TResult extends Object?>(TResult Function( int id, @JsonKey(name: 'parent_id')  int? parentId, @JsonKey(name: 'name_en')  String nameEn, @JsonKey(name: 'name_sw')  String nameSw,  String? icon, @JsonKey(name: 'sort_order')  int sortOrder)  $default,) {final _that = this;
switch (_that) {
case _SokoniCategory():
return $default(_that.id,_that.parentId,_that.nameEn,_that.nameSw,_that.icon,_that.sortOrder);case _:
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

@optionalTypeArgs TResult? whenOrNull<TResult extends Object?>(TResult? Function( int id, @JsonKey(name: 'parent_id')  int? parentId, @JsonKey(name: 'name_en')  String nameEn, @JsonKey(name: 'name_sw')  String nameSw,  String? icon, @JsonKey(name: 'sort_order')  int sortOrder)?  $default,) {final _that = this;
switch (_that) {
case _SokoniCategory() when $default != null:
return $default(_that.id,_that.parentId,_that.nameEn,_that.nameSw,_that.icon,_that.sortOrder);case _:
  return null;

}
}

}

/// @nodoc
@JsonSerializable()

class _SokoniCategory implements SokoniCategory {
  const _SokoniCategory({required this.id, @JsonKey(name: 'parent_id') this.parentId, @JsonKey(name: 'name_en') required this.nameEn, @JsonKey(name: 'name_sw') required this.nameSw, this.icon, @JsonKey(name: 'sort_order') this.sortOrder = 0});
  factory _SokoniCategory.fromJson(Map<String, dynamic> json) => _$SokoniCategoryFromJson(json);

@override final  int id;
@override@JsonKey(name: 'parent_id') final  int? parentId;
@override@JsonKey(name: 'name_en') final  String nameEn;
@override@JsonKey(name: 'name_sw') final  String nameSw;
@override final  String? icon;
@override@JsonKey(name: 'sort_order') final  int sortOrder;

/// Create a copy of SokoniCategory
/// with the given fields replaced by the non-null parameter values.
@override @JsonKey(includeFromJson: false, includeToJson: false)
@pragma('vm:prefer-inline')
_$SokoniCategoryCopyWith<_SokoniCategory> get copyWith => __$SokoniCategoryCopyWithImpl<_SokoniCategory>(this, _$identity);

@override
Map<String, dynamic> toJson() {
  return _$SokoniCategoryToJson(this, );
}

@override
bool operator ==(Object other) {
  return identical(this, other) || (other.runtimeType == runtimeType&&other is _SokoniCategory&&(identical(other.id, id) || other.id == id)&&(identical(other.parentId, parentId) || other.parentId == parentId)&&(identical(other.nameEn, nameEn) || other.nameEn == nameEn)&&(identical(other.nameSw, nameSw) || other.nameSw == nameSw)&&(identical(other.icon, icon) || other.icon == icon)&&(identical(other.sortOrder, sortOrder) || other.sortOrder == sortOrder));
}

@JsonKey(includeFromJson: false, includeToJson: false)
@override
int get hashCode => Object.hash(runtimeType,id,parentId,nameEn,nameSw,icon,sortOrder);

@override
String toString() {
  return 'SokoniCategory(id: $id, parentId: $parentId, nameEn: $nameEn, nameSw: $nameSw, icon: $icon, sortOrder: $sortOrder)';
}


}

/// @nodoc
abstract mixin class _$SokoniCategoryCopyWith<$Res> implements $SokoniCategoryCopyWith<$Res> {
  factory _$SokoniCategoryCopyWith(_SokoniCategory value, $Res Function(_SokoniCategory) _then) = __$SokoniCategoryCopyWithImpl;
@override @useResult
$Res call({
 int id,@JsonKey(name: 'parent_id') int? parentId,@JsonKey(name: 'name_en') String nameEn,@JsonKey(name: 'name_sw') String nameSw, String? icon,@JsonKey(name: 'sort_order') int sortOrder
});




}
/// @nodoc
class __$SokoniCategoryCopyWithImpl<$Res>
    implements _$SokoniCategoryCopyWith<$Res> {
  __$SokoniCategoryCopyWithImpl(this._self, this._then);

  final _SokoniCategory _self;
  final $Res Function(_SokoniCategory) _then;

/// Create a copy of SokoniCategory
/// with the given fields replaced by the non-null parameter values.
@override @pragma('vm:prefer-inline') $Res call({Object? id = null,Object? parentId = freezed,Object? nameEn = null,Object? nameSw = null,Object? icon = freezed,Object? sortOrder = null,}) {
  return _then(_SokoniCategory(
id: null == id ? _self.id : id // ignore: cast_nullable_to_non_nullable
as int,parentId: freezed == parentId ? _self.parentId : parentId // ignore: cast_nullable_to_non_nullable
as int?,nameEn: null == nameEn ? _self.nameEn : nameEn // ignore: cast_nullable_to_non_nullable
as String,nameSw: null == nameSw ? _self.nameSw : nameSw // ignore: cast_nullable_to_non_nullable
as String,icon: freezed == icon ? _self.icon : icon // ignore: cast_nullable_to_non_nullable
as String?,sortOrder: null == sortOrder ? _self.sortOrder : sortOrder // ignore: cast_nullable_to_non_nullable
as int,
  ));
}


}

// dart format on
