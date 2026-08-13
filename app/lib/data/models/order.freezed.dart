// GENERATED CODE - DO NOT MODIFY BY HAND
// coverage:ignore-file
// ignore_for_file: type=lint
// ignore_for_file: unused_element, deprecated_member_use, deprecated_member_use_from_same_package, use_function_type_syntax_for_parameters, unnecessary_const, avoid_init_to_null, invalid_override_different_default_values_named, prefer_expression_function_bodies, annotate_overrides, invalid_annotation_target, unnecessary_question_mark

part of 'order.dart';

// **************************************************************************
// FreezedGenerator
// **************************************************************************

// dart format off
T _$identity<T>(T value) => value;

/// @nodoc
mixin _$OrderTimelineStep {

 String get status; DateTime get at;
/// Create a copy of OrderTimelineStep
/// with the given fields replaced by the non-null parameter values.
@JsonKey(includeFromJson: false, includeToJson: false)
@pragma('vm:prefer-inline')
$OrderTimelineStepCopyWith<OrderTimelineStep> get copyWith => _$OrderTimelineStepCopyWithImpl<OrderTimelineStep>(this as OrderTimelineStep, _$identity);

  /// Serializes this OrderTimelineStep to a JSON map.
  Map<String, dynamic> toJson();


@override
bool operator ==(Object other) {
  return identical(this, other) || (other.runtimeType == runtimeType&&other is OrderTimelineStep&&(identical(other.status, status) || other.status == status)&&(identical(other.at, at) || other.at == at));
}

@JsonKey(includeFromJson: false, includeToJson: false)
@override
int get hashCode => Object.hash(runtimeType,status,at);

@override
String toString() {
  return 'OrderTimelineStep(status: $status, at: $at)';
}


}

/// @nodoc
abstract mixin class $OrderTimelineStepCopyWith<$Res>  {
  factory $OrderTimelineStepCopyWith(OrderTimelineStep value, $Res Function(OrderTimelineStep) _then) = _$OrderTimelineStepCopyWithImpl;
@useResult
$Res call({
 String status, DateTime at
});




}
/// @nodoc
class _$OrderTimelineStepCopyWithImpl<$Res>
    implements $OrderTimelineStepCopyWith<$Res> {
  _$OrderTimelineStepCopyWithImpl(this._self, this._then);

  final OrderTimelineStep _self;
  final $Res Function(OrderTimelineStep) _then;

/// Create a copy of OrderTimelineStep
/// with the given fields replaced by the non-null parameter values.
@pragma('vm:prefer-inline') @override $Res call({Object? status = null,Object? at = null,}) {
  return _then(_self.copyWith(
status: null == status ? _self.status : status // ignore: cast_nullable_to_non_nullable
as String,at: null == at ? _self.at : at // ignore: cast_nullable_to_non_nullable
as DateTime,
  ));
}

}


/// Adds pattern-matching-related methods to [OrderTimelineStep].
extension OrderTimelineStepPatterns on OrderTimelineStep {
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

@optionalTypeArgs TResult maybeMap<TResult extends Object?>(TResult Function( _OrderTimelineStep value)?  $default,{required TResult orElse(),}){
final _that = this;
switch (_that) {
case _OrderTimelineStep() when $default != null:
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

@optionalTypeArgs TResult map<TResult extends Object?>(TResult Function( _OrderTimelineStep value)  $default,){
final _that = this;
switch (_that) {
case _OrderTimelineStep():
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

@optionalTypeArgs TResult? mapOrNull<TResult extends Object?>(TResult? Function( _OrderTimelineStep value)?  $default,){
final _that = this;
switch (_that) {
case _OrderTimelineStep() when $default != null:
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

@optionalTypeArgs TResult maybeWhen<TResult extends Object?>(TResult Function( String status,  DateTime at)?  $default,{required TResult orElse(),}) {final _that = this;
switch (_that) {
case _OrderTimelineStep() when $default != null:
return $default(_that.status,_that.at);case _:
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

@optionalTypeArgs TResult when<TResult extends Object?>(TResult Function( String status,  DateTime at)  $default,) {final _that = this;
switch (_that) {
case _OrderTimelineStep():
return $default(_that.status,_that.at);case _:
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

@optionalTypeArgs TResult? whenOrNull<TResult extends Object?>(TResult? Function( String status,  DateTime at)?  $default,) {final _that = this;
switch (_that) {
case _OrderTimelineStep() when $default != null:
return $default(_that.status,_that.at);case _:
  return null;

}
}

}

/// @nodoc
@JsonSerializable()

class _OrderTimelineStep implements OrderTimelineStep {
  const _OrderTimelineStep({required this.status, required this.at});
  factory _OrderTimelineStep.fromJson(Map<String, dynamic> json) => _$OrderTimelineStepFromJson(json);

@override final  String status;
@override final  DateTime at;

/// Create a copy of OrderTimelineStep
/// with the given fields replaced by the non-null parameter values.
@override @JsonKey(includeFromJson: false, includeToJson: false)
@pragma('vm:prefer-inline')
_$OrderTimelineStepCopyWith<_OrderTimelineStep> get copyWith => __$OrderTimelineStepCopyWithImpl<_OrderTimelineStep>(this, _$identity);

@override
Map<String, dynamic> toJson() {
  return _$OrderTimelineStepToJson(this, );
}

@override
bool operator ==(Object other) {
  return identical(this, other) || (other.runtimeType == runtimeType&&other is _OrderTimelineStep&&(identical(other.status, status) || other.status == status)&&(identical(other.at, at) || other.at == at));
}

@JsonKey(includeFromJson: false, includeToJson: false)
@override
int get hashCode => Object.hash(runtimeType,status,at);

@override
String toString() {
  return 'OrderTimelineStep(status: $status, at: $at)';
}


}

/// @nodoc
abstract mixin class _$OrderTimelineStepCopyWith<$Res> implements $OrderTimelineStepCopyWith<$Res> {
  factory _$OrderTimelineStepCopyWith(_OrderTimelineStep value, $Res Function(_OrderTimelineStep) _then) = __$OrderTimelineStepCopyWithImpl;
@override @useResult
$Res call({
 String status, DateTime at
});




}
/// @nodoc
class __$OrderTimelineStepCopyWithImpl<$Res>
    implements _$OrderTimelineStepCopyWith<$Res> {
  __$OrderTimelineStepCopyWithImpl(this._self, this._then);

  final _OrderTimelineStep _self;
  final $Res Function(_OrderTimelineStep) _then;

/// Create a copy of OrderTimelineStep
/// with the given fields replaced by the non-null parameter values.
@override @pragma('vm:prefer-inline') $Res call({Object? status = null,Object? at = null,}) {
  return _then(_OrderTimelineStep(
status: null == status ? _self.status : status // ignore: cast_nullable_to_non_nullable
as String,at: null == at ? _self.at : at // ignore: cast_nullable_to_non_nullable
as DateTime,
  ));
}


}


/// @nodoc
mixin _$Order {

 int get id; String get code; String get status; int get subtotal;@JsonKey(name: 'delivery_fee') int get deliveryFee; int get total;@JsonKey(name: 'delivery_method') String get deliveryMethod; String? get address; String? get notes;@JsonKey(name: 'payment_method') String get paymentMethod;@JsonKey(name: 'payment_status') String? get paymentStatus;@JsonKey(name: 'cancelled_reason') String? get cancelledReason; SokoniUser? get buyer; SellerSummary? get seller; List<OrderItem> get items;@JsonKey(name: 'conversation_id') int? get conversationId; List<OrderTimelineStep> get timeline;@JsonKey(name: 'has_review') bool? get hasReview;@JsonKey(name: 'created_at') DateTime? get createdAt;
/// Create a copy of Order
/// with the given fields replaced by the non-null parameter values.
@JsonKey(includeFromJson: false, includeToJson: false)
@pragma('vm:prefer-inline')
$OrderCopyWith<Order> get copyWith => _$OrderCopyWithImpl<Order>(this as Order, _$identity);

  /// Serializes this Order to a JSON map.
  Map<String, dynamic> toJson();


@override
bool operator ==(Object other) {
  return identical(this, other) || (other.runtimeType == runtimeType&&other is Order&&(identical(other.id, id) || other.id == id)&&(identical(other.code, code) || other.code == code)&&(identical(other.status, status) || other.status == status)&&(identical(other.subtotal, subtotal) || other.subtotal == subtotal)&&(identical(other.deliveryFee, deliveryFee) || other.deliveryFee == deliveryFee)&&(identical(other.total, total) || other.total == total)&&(identical(other.deliveryMethod, deliveryMethod) || other.deliveryMethod == deliveryMethod)&&(identical(other.address, address) || other.address == address)&&(identical(other.notes, notes) || other.notes == notes)&&(identical(other.paymentMethod, paymentMethod) || other.paymentMethod == paymentMethod)&&(identical(other.paymentStatus, paymentStatus) || other.paymentStatus == paymentStatus)&&(identical(other.cancelledReason, cancelledReason) || other.cancelledReason == cancelledReason)&&(identical(other.buyer, buyer) || other.buyer == buyer)&&(identical(other.seller, seller) || other.seller == seller)&&const DeepCollectionEquality().equals(other.items, items)&&(identical(other.conversationId, conversationId) || other.conversationId == conversationId)&&const DeepCollectionEquality().equals(other.timeline, timeline)&&(identical(other.hasReview, hasReview) || other.hasReview == hasReview)&&(identical(other.createdAt, createdAt) || other.createdAt == createdAt));
}

@JsonKey(includeFromJson: false, includeToJson: false)
@override
int get hashCode => Object.hashAll([runtimeType,id,code,status,subtotal,deliveryFee,total,deliveryMethod,address,notes,paymentMethod,paymentStatus,cancelledReason,buyer,seller,const DeepCollectionEquality().hash(items),conversationId,const DeepCollectionEquality().hash(timeline),hasReview,createdAt]);

@override
String toString() {
  return 'Order(id: $id, code: $code, status: $status, subtotal: $subtotal, deliveryFee: $deliveryFee, total: $total, deliveryMethod: $deliveryMethod, address: $address, notes: $notes, paymentMethod: $paymentMethod, paymentStatus: $paymentStatus, cancelledReason: $cancelledReason, buyer: $buyer, seller: $seller, items: $items, conversationId: $conversationId, timeline: $timeline, hasReview: $hasReview, createdAt: $createdAt)';
}


}

/// @nodoc
abstract mixin class $OrderCopyWith<$Res>  {
  factory $OrderCopyWith(Order value, $Res Function(Order) _then) = _$OrderCopyWithImpl;
@useResult
$Res call({
 int id, String code, String status, int subtotal,@JsonKey(name: 'delivery_fee') int deliveryFee, int total,@JsonKey(name: 'delivery_method') String deliveryMethod, String? address, String? notes,@JsonKey(name: 'payment_method') String paymentMethod,@JsonKey(name: 'payment_status') String? paymentStatus,@JsonKey(name: 'cancelled_reason') String? cancelledReason, SokoniUser? buyer, SellerSummary? seller, List<OrderItem> items,@JsonKey(name: 'conversation_id') int? conversationId, List<OrderTimelineStep> timeline,@JsonKey(name: 'has_review') bool? hasReview,@JsonKey(name: 'created_at') DateTime? createdAt
});


$SokoniUserCopyWith<$Res>? get buyer;$SellerSummaryCopyWith<$Res>? get seller;

}
/// @nodoc
class _$OrderCopyWithImpl<$Res>
    implements $OrderCopyWith<$Res> {
  _$OrderCopyWithImpl(this._self, this._then);

  final Order _self;
  final $Res Function(Order) _then;

/// Create a copy of Order
/// with the given fields replaced by the non-null parameter values.
@pragma('vm:prefer-inline') @override $Res call({Object? id = null,Object? code = null,Object? status = null,Object? subtotal = null,Object? deliveryFee = null,Object? total = null,Object? deliveryMethod = null,Object? address = freezed,Object? notes = freezed,Object? paymentMethod = null,Object? paymentStatus = freezed,Object? cancelledReason = freezed,Object? buyer = freezed,Object? seller = freezed,Object? items = null,Object? conversationId = freezed,Object? timeline = null,Object? hasReview = freezed,Object? createdAt = freezed,}) {
  return _then(_self.copyWith(
id: null == id ? _self.id : id // ignore: cast_nullable_to_non_nullable
as int,code: null == code ? _self.code : code // ignore: cast_nullable_to_non_nullable
as String,status: null == status ? _self.status : status // ignore: cast_nullable_to_non_nullable
as String,subtotal: null == subtotal ? _self.subtotal : subtotal // ignore: cast_nullable_to_non_nullable
as int,deliveryFee: null == deliveryFee ? _self.deliveryFee : deliveryFee // ignore: cast_nullable_to_non_nullable
as int,total: null == total ? _self.total : total // ignore: cast_nullable_to_non_nullable
as int,deliveryMethod: null == deliveryMethod ? _self.deliveryMethod : deliveryMethod // ignore: cast_nullable_to_non_nullable
as String,address: freezed == address ? _self.address : address // ignore: cast_nullable_to_non_nullable
as String?,notes: freezed == notes ? _self.notes : notes // ignore: cast_nullable_to_non_nullable
as String?,paymentMethod: null == paymentMethod ? _self.paymentMethod : paymentMethod // ignore: cast_nullable_to_non_nullable
as String,paymentStatus: freezed == paymentStatus ? _self.paymentStatus : paymentStatus // ignore: cast_nullable_to_non_nullable
as String?,cancelledReason: freezed == cancelledReason ? _self.cancelledReason : cancelledReason // ignore: cast_nullable_to_non_nullable
as String?,buyer: freezed == buyer ? _self.buyer : buyer // ignore: cast_nullable_to_non_nullable
as SokoniUser?,seller: freezed == seller ? _self.seller : seller // ignore: cast_nullable_to_non_nullable
as SellerSummary?,items: null == items ? _self.items : items // ignore: cast_nullable_to_non_nullable
as List<OrderItem>,conversationId: freezed == conversationId ? _self.conversationId : conversationId // ignore: cast_nullable_to_non_nullable
as int?,timeline: null == timeline ? _self.timeline : timeline // ignore: cast_nullable_to_non_nullable
as List<OrderTimelineStep>,hasReview: freezed == hasReview ? _self.hasReview : hasReview // ignore: cast_nullable_to_non_nullable
as bool?,createdAt: freezed == createdAt ? _self.createdAt : createdAt // ignore: cast_nullable_to_non_nullable
as DateTime?,
  ));
}
/// Create a copy of Order
/// with the given fields replaced by the non-null parameter values.
@override
@pragma('vm:prefer-inline')
$SokoniUserCopyWith<$Res>? get buyer {
    if (_self.buyer == null) {
    return null;
  }

  return $SokoniUserCopyWith<$Res>(_self.buyer!, (value) {
    return _then(_self.copyWith(buyer: value));
  });
}/// Create a copy of Order
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
}
}


/// Adds pattern-matching-related methods to [Order].
extension OrderPatterns on Order {
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

@optionalTypeArgs TResult maybeMap<TResult extends Object?>(TResult Function( _Order value)?  $default,{required TResult orElse(),}){
final _that = this;
switch (_that) {
case _Order() when $default != null:
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

@optionalTypeArgs TResult map<TResult extends Object?>(TResult Function( _Order value)  $default,){
final _that = this;
switch (_that) {
case _Order():
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

@optionalTypeArgs TResult? mapOrNull<TResult extends Object?>(TResult? Function( _Order value)?  $default,){
final _that = this;
switch (_that) {
case _Order() when $default != null:
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

@optionalTypeArgs TResult maybeWhen<TResult extends Object?>(TResult Function( int id,  String code,  String status,  int subtotal, @JsonKey(name: 'delivery_fee')  int deliveryFee,  int total, @JsonKey(name: 'delivery_method')  String deliveryMethod,  String? address,  String? notes, @JsonKey(name: 'payment_method')  String paymentMethod, @JsonKey(name: 'payment_status')  String? paymentStatus, @JsonKey(name: 'cancelled_reason')  String? cancelledReason,  SokoniUser? buyer,  SellerSummary? seller,  List<OrderItem> items, @JsonKey(name: 'conversation_id')  int? conversationId,  List<OrderTimelineStep> timeline, @JsonKey(name: 'has_review')  bool? hasReview, @JsonKey(name: 'created_at')  DateTime? createdAt)?  $default,{required TResult orElse(),}) {final _that = this;
switch (_that) {
case _Order() when $default != null:
return $default(_that.id,_that.code,_that.status,_that.subtotal,_that.deliveryFee,_that.total,_that.deliveryMethod,_that.address,_that.notes,_that.paymentMethod,_that.paymentStatus,_that.cancelledReason,_that.buyer,_that.seller,_that.items,_that.conversationId,_that.timeline,_that.hasReview,_that.createdAt);case _:
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

@optionalTypeArgs TResult when<TResult extends Object?>(TResult Function( int id,  String code,  String status,  int subtotal, @JsonKey(name: 'delivery_fee')  int deliveryFee,  int total, @JsonKey(name: 'delivery_method')  String deliveryMethod,  String? address,  String? notes, @JsonKey(name: 'payment_method')  String paymentMethod, @JsonKey(name: 'payment_status')  String? paymentStatus, @JsonKey(name: 'cancelled_reason')  String? cancelledReason,  SokoniUser? buyer,  SellerSummary? seller,  List<OrderItem> items, @JsonKey(name: 'conversation_id')  int? conversationId,  List<OrderTimelineStep> timeline, @JsonKey(name: 'has_review')  bool? hasReview, @JsonKey(name: 'created_at')  DateTime? createdAt)  $default,) {final _that = this;
switch (_that) {
case _Order():
return $default(_that.id,_that.code,_that.status,_that.subtotal,_that.deliveryFee,_that.total,_that.deliveryMethod,_that.address,_that.notes,_that.paymentMethod,_that.paymentStatus,_that.cancelledReason,_that.buyer,_that.seller,_that.items,_that.conversationId,_that.timeline,_that.hasReview,_that.createdAt);case _:
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

@optionalTypeArgs TResult? whenOrNull<TResult extends Object?>(TResult? Function( int id,  String code,  String status,  int subtotal, @JsonKey(name: 'delivery_fee')  int deliveryFee,  int total, @JsonKey(name: 'delivery_method')  String deliveryMethod,  String? address,  String? notes, @JsonKey(name: 'payment_method')  String paymentMethod, @JsonKey(name: 'payment_status')  String? paymentStatus, @JsonKey(name: 'cancelled_reason')  String? cancelledReason,  SokoniUser? buyer,  SellerSummary? seller,  List<OrderItem> items, @JsonKey(name: 'conversation_id')  int? conversationId,  List<OrderTimelineStep> timeline, @JsonKey(name: 'has_review')  bool? hasReview, @JsonKey(name: 'created_at')  DateTime? createdAt)?  $default,) {final _that = this;
switch (_that) {
case _Order() when $default != null:
return $default(_that.id,_that.code,_that.status,_that.subtotal,_that.deliveryFee,_that.total,_that.deliveryMethod,_that.address,_that.notes,_that.paymentMethod,_that.paymentStatus,_that.cancelledReason,_that.buyer,_that.seller,_that.items,_that.conversationId,_that.timeline,_that.hasReview,_that.createdAt);case _:
  return null;

}
}

}

/// @nodoc
@JsonSerializable()

class _Order implements Order {
  const _Order({required this.id, required this.code, required this.status, required this.subtotal, @JsonKey(name: 'delivery_fee') required this.deliveryFee, required this.total, @JsonKey(name: 'delivery_method') required this.deliveryMethod, this.address, this.notes, @JsonKey(name: 'payment_method') required this.paymentMethod, @JsonKey(name: 'payment_status') this.paymentStatus, @JsonKey(name: 'cancelled_reason') this.cancelledReason, this.buyer, this.seller, final  List<OrderItem> items = const <OrderItem>[], @JsonKey(name: 'conversation_id') this.conversationId, final  List<OrderTimelineStep> timeline = const <OrderTimelineStep>[], @JsonKey(name: 'has_review') this.hasReview, @JsonKey(name: 'created_at') this.createdAt}): _items = items,_timeline = timeline;
  factory _Order.fromJson(Map<String, dynamic> json) => _$OrderFromJson(json);

@override final  int id;
@override final  String code;
@override final  String status;
@override final  int subtotal;
@override@JsonKey(name: 'delivery_fee') final  int deliveryFee;
@override final  int total;
@override@JsonKey(name: 'delivery_method') final  String deliveryMethod;
@override final  String? address;
@override final  String? notes;
@override@JsonKey(name: 'payment_method') final  String paymentMethod;
@override@JsonKey(name: 'payment_status') final  String? paymentStatus;
@override@JsonKey(name: 'cancelled_reason') final  String? cancelledReason;
@override final  SokoniUser? buyer;
@override final  SellerSummary? seller;
 final  List<OrderItem> _items;
@override@JsonKey() List<OrderItem> get items {
  if (_items is EqualUnmodifiableListView) return _items;
  // ignore: implicit_dynamic_type
  return EqualUnmodifiableListView(_items);
}

@override@JsonKey(name: 'conversation_id') final  int? conversationId;
 final  List<OrderTimelineStep> _timeline;
@override@JsonKey() List<OrderTimelineStep> get timeline {
  if (_timeline is EqualUnmodifiableListView) return _timeline;
  // ignore: implicit_dynamic_type
  return EqualUnmodifiableListView(_timeline);
}

@override@JsonKey(name: 'has_review') final  bool? hasReview;
@override@JsonKey(name: 'created_at') final  DateTime? createdAt;

/// Create a copy of Order
/// with the given fields replaced by the non-null parameter values.
@override @JsonKey(includeFromJson: false, includeToJson: false)
@pragma('vm:prefer-inline')
_$OrderCopyWith<_Order> get copyWith => __$OrderCopyWithImpl<_Order>(this, _$identity);

@override
Map<String, dynamic> toJson() {
  return _$OrderToJson(this, );
}

@override
bool operator ==(Object other) {
  return identical(this, other) || (other.runtimeType == runtimeType&&other is _Order&&(identical(other.id, id) || other.id == id)&&(identical(other.code, code) || other.code == code)&&(identical(other.status, status) || other.status == status)&&(identical(other.subtotal, subtotal) || other.subtotal == subtotal)&&(identical(other.deliveryFee, deliveryFee) || other.deliveryFee == deliveryFee)&&(identical(other.total, total) || other.total == total)&&(identical(other.deliveryMethod, deliveryMethod) || other.deliveryMethod == deliveryMethod)&&(identical(other.address, address) || other.address == address)&&(identical(other.notes, notes) || other.notes == notes)&&(identical(other.paymentMethod, paymentMethod) || other.paymentMethod == paymentMethod)&&(identical(other.paymentStatus, paymentStatus) || other.paymentStatus == paymentStatus)&&(identical(other.cancelledReason, cancelledReason) || other.cancelledReason == cancelledReason)&&(identical(other.buyer, buyer) || other.buyer == buyer)&&(identical(other.seller, seller) || other.seller == seller)&&const DeepCollectionEquality().equals(other._items, _items)&&(identical(other.conversationId, conversationId) || other.conversationId == conversationId)&&const DeepCollectionEquality().equals(other._timeline, _timeline)&&(identical(other.hasReview, hasReview) || other.hasReview == hasReview)&&(identical(other.createdAt, createdAt) || other.createdAt == createdAt));
}

@JsonKey(includeFromJson: false, includeToJson: false)
@override
int get hashCode => Object.hashAll([runtimeType,id,code,status,subtotal,deliveryFee,total,deliveryMethod,address,notes,paymentMethod,paymentStatus,cancelledReason,buyer,seller,const DeepCollectionEquality().hash(_items),conversationId,const DeepCollectionEquality().hash(_timeline),hasReview,createdAt]);

@override
String toString() {
  return 'Order(id: $id, code: $code, status: $status, subtotal: $subtotal, deliveryFee: $deliveryFee, total: $total, deliveryMethod: $deliveryMethod, address: $address, notes: $notes, paymentMethod: $paymentMethod, paymentStatus: $paymentStatus, cancelledReason: $cancelledReason, buyer: $buyer, seller: $seller, items: $items, conversationId: $conversationId, timeline: $timeline, hasReview: $hasReview, createdAt: $createdAt)';
}


}

/// @nodoc
abstract mixin class _$OrderCopyWith<$Res> implements $OrderCopyWith<$Res> {
  factory _$OrderCopyWith(_Order value, $Res Function(_Order) _then) = __$OrderCopyWithImpl;
@override @useResult
$Res call({
 int id, String code, String status, int subtotal,@JsonKey(name: 'delivery_fee') int deliveryFee, int total,@JsonKey(name: 'delivery_method') String deliveryMethod, String? address, String? notes,@JsonKey(name: 'payment_method') String paymentMethod,@JsonKey(name: 'payment_status') String? paymentStatus,@JsonKey(name: 'cancelled_reason') String? cancelledReason, SokoniUser? buyer, SellerSummary? seller, List<OrderItem> items,@JsonKey(name: 'conversation_id') int? conversationId, List<OrderTimelineStep> timeline,@JsonKey(name: 'has_review') bool? hasReview,@JsonKey(name: 'created_at') DateTime? createdAt
});


@override $SokoniUserCopyWith<$Res>? get buyer;@override $SellerSummaryCopyWith<$Res>? get seller;

}
/// @nodoc
class __$OrderCopyWithImpl<$Res>
    implements _$OrderCopyWith<$Res> {
  __$OrderCopyWithImpl(this._self, this._then);

  final _Order _self;
  final $Res Function(_Order) _then;

/// Create a copy of Order
/// with the given fields replaced by the non-null parameter values.
@override @pragma('vm:prefer-inline') $Res call({Object? id = null,Object? code = null,Object? status = null,Object? subtotal = null,Object? deliveryFee = null,Object? total = null,Object? deliveryMethod = null,Object? address = freezed,Object? notes = freezed,Object? paymentMethod = null,Object? paymentStatus = freezed,Object? cancelledReason = freezed,Object? buyer = freezed,Object? seller = freezed,Object? items = null,Object? conversationId = freezed,Object? timeline = null,Object? hasReview = freezed,Object? createdAt = freezed,}) {
  return _then(_Order(
id: null == id ? _self.id : id // ignore: cast_nullable_to_non_nullable
as int,code: null == code ? _self.code : code // ignore: cast_nullable_to_non_nullable
as String,status: null == status ? _self.status : status // ignore: cast_nullable_to_non_nullable
as String,subtotal: null == subtotal ? _self.subtotal : subtotal // ignore: cast_nullable_to_non_nullable
as int,deliveryFee: null == deliveryFee ? _self.deliveryFee : deliveryFee // ignore: cast_nullable_to_non_nullable
as int,total: null == total ? _self.total : total // ignore: cast_nullable_to_non_nullable
as int,deliveryMethod: null == deliveryMethod ? _self.deliveryMethod : deliveryMethod // ignore: cast_nullable_to_non_nullable
as String,address: freezed == address ? _self.address : address // ignore: cast_nullable_to_non_nullable
as String?,notes: freezed == notes ? _self.notes : notes // ignore: cast_nullable_to_non_nullable
as String?,paymentMethod: null == paymentMethod ? _self.paymentMethod : paymentMethod // ignore: cast_nullable_to_non_nullable
as String,paymentStatus: freezed == paymentStatus ? _self.paymentStatus : paymentStatus // ignore: cast_nullable_to_non_nullable
as String?,cancelledReason: freezed == cancelledReason ? _self.cancelledReason : cancelledReason // ignore: cast_nullable_to_non_nullable
as String?,buyer: freezed == buyer ? _self.buyer : buyer // ignore: cast_nullable_to_non_nullable
as SokoniUser?,seller: freezed == seller ? _self.seller : seller // ignore: cast_nullable_to_non_nullable
as SellerSummary?,items: null == items ? _self._items : items // ignore: cast_nullable_to_non_nullable
as List<OrderItem>,conversationId: freezed == conversationId ? _self.conversationId : conversationId // ignore: cast_nullable_to_non_nullable
as int?,timeline: null == timeline ? _self._timeline : timeline // ignore: cast_nullable_to_non_nullable
as List<OrderTimelineStep>,hasReview: freezed == hasReview ? _self.hasReview : hasReview // ignore: cast_nullable_to_non_nullable
as bool?,createdAt: freezed == createdAt ? _self.createdAt : createdAt // ignore: cast_nullable_to_non_nullable
as DateTime?,
  ));
}

/// Create a copy of Order
/// with the given fields replaced by the non-null parameter values.
@override
@pragma('vm:prefer-inline')
$SokoniUserCopyWith<$Res>? get buyer {
    if (_self.buyer == null) {
    return null;
  }

  return $SokoniUserCopyWith<$Res>(_self.buyer!, (value) {
    return _then(_self.copyWith(buyer: value));
  });
}/// Create a copy of Order
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
}
}

// dart format on
