// GENERATED CODE - DO NOT MODIFY BY HAND
// coverage:ignore-file
// ignore_for_file: type=lint
// ignore_for_file: unused_element, deprecated_member_use, deprecated_member_use_from_same_package, use_function_type_syntax_for_parameters, unnecessary_const, avoid_init_to_null, invalid_override_different_default_values_named, prefer_expression_function_bodies, annotate_overrides, invalid_annotation_target, unnecessary_question_mark

part of 'seller_dashboard_stats.dart';

// **************************************************************************
// FreezedGenerator
// **************************************************************************

// dart format off
T _$identity<T>(T value) => value;

/// @nodoc
mixin _$SellerDashboardStats {

@JsonKey(name: 'total_views') int get totalViews;@JsonKey(name: 'saves_last_30_days') int get savesLast30Days;@JsonKey(name: 'orders_last_30_days') int get ordersLast30Days;
/// Create a copy of SellerDashboardStats
/// with the given fields replaced by the non-null parameter values.
@JsonKey(includeFromJson: false, includeToJson: false)
@pragma('vm:prefer-inline')
$SellerDashboardStatsCopyWith<SellerDashboardStats> get copyWith => _$SellerDashboardStatsCopyWithImpl<SellerDashboardStats>(this as SellerDashboardStats, _$identity);

  /// Serializes this SellerDashboardStats to a JSON map.
  Map<String, dynamic> toJson();


@override
bool operator ==(Object other) {
  return identical(this, other) || (other.runtimeType == runtimeType&&other is SellerDashboardStats&&(identical(other.totalViews, totalViews) || other.totalViews == totalViews)&&(identical(other.savesLast30Days, savesLast30Days) || other.savesLast30Days == savesLast30Days)&&(identical(other.ordersLast30Days, ordersLast30Days) || other.ordersLast30Days == ordersLast30Days));
}

@JsonKey(includeFromJson: false, includeToJson: false)
@override
int get hashCode => Object.hash(runtimeType,totalViews,savesLast30Days,ordersLast30Days);

@override
String toString() {
  return 'SellerDashboardStats(totalViews: $totalViews, savesLast30Days: $savesLast30Days, ordersLast30Days: $ordersLast30Days)';
}


}

/// @nodoc
abstract mixin class $SellerDashboardStatsCopyWith<$Res>  {
  factory $SellerDashboardStatsCopyWith(SellerDashboardStats value, $Res Function(SellerDashboardStats) _then) = _$SellerDashboardStatsCopyWithImpl;
@useResult
$Res call({
@JsonKey(name: 'total_views') int totalViews,@JsonKey(name: 'saves_last_30_days') int savesLast30Days,@JsonKey(name: 'orders_last_30_days') int ordersLast30Days
});




}
/// @nodoc
class _$SellerDashboardStatsCopyWithImpl<$Res>
    implements $SellerDashboardStatsCopyWith<$Res> {
  _$SellerDashboardStatsCopyWithImpl(this._self, this._then);

  final SellerDashboardStats _self;
  final $Res Function(SellerDashboardStats) _then;

/// Create a copy of SellerDashboardStats
/// with the given fields replaced by the non-null parameter values.
@pragma('vm:prefer-inline') @override $Res call({Object? totalViews = null,Object? savesLast30Days = null,Object? ordersLast30Days = null,}) {
  return _then(_self.copyWith(
totalViews: null == totalViews ? _self.totalViews : totalViews // ignore: cast_nullable_to_non_nullable
as int,savesLast30Days: null == savesLast30Days ? _self.savesLast30Days : savesLast30Days // ignore: cast_nullable_to_non_nullable
as int,ordersLast30Days: null == ordersLast30Days ? _self.ordersLast30Days : ordersLast30Days // ignore: cast_nullable_to_non_nullable
as int,
  ));
}

}


/// Adds pattern-matching-related methods to [SellerDashboardStats].
extension SellerDashboardStatsPatterns on SellerDashboardStats {
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

@optionalTypeArgs TResult maybeMap<TResult extends Object?>(TResult Function( _SellerDashboardStats value)?  $default,{required TResult orElse(),}){
final _that = this;
switch (_that) {
case _SellerDashboardStats() when $default != null:
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

@optionalTypeArgs TResult map<TResult extends Object?>(TResult Function( _SellerDashboardStats value)  $default,){
final _that = this;
switch (_that) {
case _SellerDashboardStats():
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

@optionalTypeArgs TResult? mapOrNull<TResult extends Object?>(TResult? Function( _SellerDashboardStats value)?  $default,){
final _that = this;
switch (_that) {
case _SellerDashboardStats() when $default != null:
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

@optionalTypeArgs TResult maybeWhen<TResult extends Object?>(TResult Function(@JsonKey(name: 'total_views')  int totalViews, @JsonKey(name: 'saves_last_30_days')  int savesLast30Days, @JsonKey(name: 'orders_last_30_days')  int ordersLast30Days)?  $default,{required TResult orElse(),}) {final _that = this;
switch (_that) {
case _SellerDashboardStats() when $default != null:
return $default(_that.totalViews,_that.savesLast30Days,_that.ordersLast30Days);case _:
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

@optionalTypeArgs TResult when<TResult extends Object?>(TResult Function(@JsonKey(name: 'total_views')  int totalViews, @JsonKey(name: 'saves_last_30_days')  int savesLast30Days, @JsonKey(name: 'orders_last_30_days')  int ordersLast30Days)  $default,) {final _that = this;
switch (_that) {
case _SellerDashboardStats():
return $default(_that.totalViews,_that.savesLast30Days,_that.ordersLast30Days);case _:
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

@optionalTypeArgs TResult? whenOrNull<TResult extends Object?>(TResult? Function(@JsonKey(name: 'total_views')  int totalViews, @JsonKey(name: 'saves_last_30_days')  int savesLast30Days, @JsonKey(name: 'orders_last_30_days')  int ordersLast30Days)?  $default,) {final _that = this;
switch (_that) {
case _SellerDashboardStats() when $default != null:
return $default(_that.totalViews,_that.savesLast30Days,_that.ordersLast30Days);case _:
  return null;

}
}

}

/// @nodoc
@JsonSerializable()

class _SellerDashboardStats implements SellerDashboardStats {
  const _SellerDashboardStats({@JsonKey(name: 'total_views') this.totalViews = 0, @JsonKey(name: 'saves_last_30_days') this.savesLast30Days = 0, @JsonKey(name: 'orders_last_30_days') this.ordersLast30Days = 0});
  factory _SellerDashboardStats.fromJson(Map<String, dynamic> json) => _$SellerDashboardStatsFromJson(json);

@override@JsonKey(name: 'total_views') final  int totalViews;
@override@JsonKey(name: 'saves_last_30_days') final  int savesLast30Days;
@override@JsonKey(name: 'orders_last_30_days') final  int ordersLast30Days;

/// Create a copy of SellerDashboardStats
/// with the given fields replaced by the non-null parameter values.
@override @JsonKey(includeFromJson: false, includeToJson: false)
@pragma('vm:prefer-inline')
_$SellerDashboardStatsCopyWith<_SellerDashboardStats> get copyWith => __$SellerDashboardStatsCopyWithImpl<_SellerDashboardStats>(this, _$identity);

@override
Map<String, dynamic> toJson() {
  return _$SellerDashboardStatsToJson(this, );
}

@override
bool operator ==(Object other) {
  return identical(this, other) || (other.runtimeType == runtimeType&&other is _SellerDashboardStats&&(identical(other.totalViews, totalViews) || other.totalViews == totalViews)&&(identical(other.savesLast30Days, savesLast30Days) || other.savesLast30Days == savesLast30Days)&&(identical(other.ordersLast30Days, ordersLast30Days) || other.ordersLast30Days == ordersLast30Days));
}

@JsonKey(includeFromJson: false, includeToJson: false)
@override
int get hashCode => Object.hash(runtimeType,totalViews,savesLast30Days,ordersLast30Days);

@override
String toString() {
  return 'SellerDashboardStats(totalViews: $totalViews, savesLast30Days: $savesLast30Days, ordersLast30Days: $ordersLast30Days)';
}


}

/// @nodoc
abstract mixin class _$SellerDashboardStatsCopyWith<$Res> implements $SellerDashboardStatsCopyWith<$Res> {
  factory _$SellerDashboardStatsCopyWith(_SellerDashboardStats value, $Res Function(_SellerDashboardStats) _then) = __$SellerDashboardStatsCopyWithImpl;
@override @useResult
$Res call({
@JsonKey(name: 'total_views') int totalViews,@JsonKey(name: 'saves_last_30_days') int savesLast30Days,@JsonKey(name: 'orders_last_30_days') int ordersLast30Days
});




}
/// @nodoc
class __$SellerDashboardStatsCopyWithImpl<$Res>
    implements _$SellerDashboardStatsCopyWith<$Res> {
  __$SellerDashboardStatsCopyWithImpl(this._self, this._then);

  final _SellerDashboardStats _self;
  final $Res Function(_SellerDashboardStats) _then;

/// Create a copy of SellerDashboardStats
/// with the given fields replaced by the non-null parameter values.
@override @pragma('vm:prefer-inline') $Res call({Object? totalViews = null,Object? savesLast30Days = null,Object? ordersLast30Days = null,}) {
  return _then(_SellerDashboardStats(
totalViews: null == totalViews ? _self.totalViews : totalViews // ignore: cast_nullable_to_non_nullable
as int,savesLast30Days: null == savesLast30Days ? _self.savesLast30Days : savesLast30Days // ignore: cast_nullable_to_non_nullable
as int,ordersLast30Days: null == ordersLast30Days ? _self.ordersLast30Days : ordersLast30Days // ignore: cast_nullable_to_non_nullable
as int,
  ));
}


}

// dart format on
