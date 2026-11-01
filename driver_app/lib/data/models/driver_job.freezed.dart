// GENERATED CODE - DO NOT MODIFY BY HAND
// coverage:ignore-file
// ignore_for_file: type=lint
// ignore_for_file: unused_element, deprecated_member_use, deprecated_member_use_from_same_package, use_function_type_syntax_for_parameters, unnecessary_const, avoid_init_to_null, invalid_override_different_default_values_named, prefer_expression_function_bodies, annotate_overrides, invalid_annotation_target, unnecessary_question_mark

part of 'driver_job.dart';

// **************************************************************************
// FreezedGenerator
// **************************************************************************

// dart format off
T _$identity<T>(T value) => value;

/// @nodoc
mixin _$DriverJob {

 int get id;@JsonKey(name: 'order_id') int get orderId;/// Masked (`SKAB12••••`) until pickup: its last 4 characters are the
/// pickup code the seller gives the driver.
@JsonKey(name: 'order_code') String get orderCode; String get status;@JsonKey(name: 'is_active') bool get isActive;@JsonKey(name: 'distance_km') double? get distanceKm; List<TimelineEntry> get timeline;@JsonKey(name: 'cancelled_by') String? get cancelledBy;@JsonKey(name: 'cancel_reason') String? get cancelReason;@JsonKey(name: 'failure_reason') String? get failureReason;@JsonKey(name: 'created_at') DateTime? get createdAt; int get fee; int get commission;@JsonKey(name: 'driver_earnings') int get driverEarnings;@JsonKey(name: 'earned_amount') int? get earnedAmount;@JsonKey(name: 'payment_status') String? get paymentStatus;@JsonKey(name: 'collect_cash') int get collectCash; JobPlace get shop; JobPlace get customer; List<JobItem> get items; String? get notes;@JsonKey(name: 'call_attempts') int get callAttempts;@JsonKey(name: 'pin_attempts_left') int get pinAttemptsLeft;@JsonKey(name: 'pin_locked') bool get pinLocked;@JsonKey(name: 'proof_photo_required') bool get proofPhotoRequired;@JsonKey(name: 'unreachable_available_at') DateTime? get unreachableAvailableAt; JobActions get actions;
/// Create a copy of DriverJob
/// with the given fields replaced by the non-null parameter values.
@JsonKey(includeFromJson: false, includeToJson: false)
@pragma('vm:prefer-inline')
$DriverJobCopyWith<DriverJob> get copyWith => _$DriverJobCopyWithImpl<DriverJob>(this as DriverJob, _$identity);

  /// Serializes this DriverJob to a JSON map.
  Map<String, dynamic> toJson();


@override
bool operator ==(Object other) {
  return identical(this, other) || (other.runtimeType == runtimeType&&other is DriverJob&&(identical(other.id, id) || other.id == id)&&(identical(other.orderId, orderId) || other.orderId == orderId)&&(identical(other.orderCode, orderCode) || other.orderCode == orderCode)&&(identical(other.status, status) || other.status == status)&&(identical(other.isActive, isActive) || other.isActive == isActive)&&(identical(other.distanceKm, distanceKm) || other.distanceKm == distanceKm)&&const DeepCollectionEquality().equals(other.timeline, timeline)&&(identical(other.cancelledBy, cancelledBy) || other.cancelledBy == cancelledBy)&&(identical(other.cancelReason, cancelReason) || other.cancelReason == cancelReason)&&(identical(other.failureReason, failureReason) || other.failureReason == failureReason)&&(identical(other.createdAt, createdAt) || other.createdAt == createdAt)&&(identical(other.fee, fee) || other.fee == fee)&&(identical(other.commission, commission) || other.commission == commission)&&(identical(other.driverEarnings, driverEarnings) || other.driverEarnings == driverEarnings)&&(identical(other.earnedAmount, earnedAmount) || other.earnedAmount == earnedAmount)&&(identical(other.paymentStatus, paymentStatus) || other.paymentStatus == paymentStatus)&&(identical(other.collectCash, collectCash) || other.collectCash == collectCash)&&(identical(other.shop, shop) || other.shop == shop)&&(identical(other.customer, customer) || other.customer == customer)&&const DeepCollectionEquality().equals(other.items, items)&&(identical(other.notes, notes) || other.notes == notes)&&(identical(other.callAttempts, callAttempts) || other.callAttempts == callAttempts)&&(identical(other.pinAttemptsLeft, pinAttemptsLeft) || other.pinAttemptsLeft == pinAttemptsLeft)&&(identical(other.pinLocked, pinLocked) || other.pinLocked == pinLocked)&&(identical(other.proofPhotoRequired, proofPhotoRequired) || other.proofPhotoRequired == proofPhotoRequired)&&(identical(other.unreachableAvailableAt, unreachableAvailableAt) || other.unreachableAvailableAt == unreachableAvailableAt)&&(identical(other.actions, actions) || other.actions == actions));
}

@JsonKey(includeFromJson: false, includeToJson: false)
@override
int get hashCode => Object.hashAll([runtimeType,id,orderId,orderCode,status,isActive,distanceKm,const DeepCollectionEquality().hash(timeline),cancelledBy,cancelReason,failureReason,createdAt,fee,commission,driverEarnings,earnedAmount,paymentStatus,collectCash,shop,customer,const DeepCollectionEquality().hash(items),notes,callAttempts,pinAttemptsLeft,pinLocked,proofPhotoRequired,unreachableAvailableAt,actions]);

@override
String toString() {
  return 'DriverJob(id: $id, orderId: $orderId, orderCode: $orderCode, status: $status, isActive: $isActive, distanceKm: $distanceKm, timeline: $timeline, cancelledBy: $cancelledBy, cancelReason: $cancelReason, failureReason: $failureReason, createdAt: $createdAt, fee: $fee, commission: $commission, driverEarnings: $driverEarnings, earnedAmount: $earnedAmount, paymentStatus: $paymentStatus, collectCash: $collectCash, shop: $shop, customer: $customer, items: $items, notes: $notes, callAttempts: $callAttempts, pinAttemptsLeft: $pinAttemptsLeft, pinLocked: $pinLocked, proofPhotoRequired: $proofPhotoRequired, unreachableAvailableAt: $unreachableAvailableAt, actions: $actions)';
}


}

/// @nodoc
abstract mixin class $DriverJobCopyWith<$Res>  {
  factory $DriverJobCopyWith(DriverJob value, $Res Function(DriverJob) _then) = _$DriverJobCopyWithImpl;
@useResult
$Res call({
 int id,@JsonKey(name: 'order_id') int orderId,@JsonKey(name: 'order_code') String orderCode, String status,@JsonKey(name: 'is_active') bool isActive,@JsonKey(name: 'distance_km') double? distanceKm, List<TimelineEntry> timeline,@JsonKey(name: 'cancelled_by') String? cancelledBy,@JsonKey(name: 'cancel_reason') String? cancelReason,@JsonKey(name: 'failure_reason') String? failureReason,@JsonKey(name: 'created_at') DateTime? createdAt, int fee, int commission,@JsonKey(name: 'driver_earnings') int driverEarnings,@JsonKey(name: 'earned_amount') int? earnedAmount,@JsonKey(name: 'payment_status') String? paymentStatus,@JsonKey(name: 'collect_cash') int collectCash, JobPlace shop, JobPlace customer, List<JobItem> items, String? notes,@JsonKey(name: 'call_attempts') int callAttempts,@JsonKey(name: 'pin_attempts_left') int pinAttemptsLeft,@JsonKey(name: 'pin_locked') bool pinLocked,@JsonKey(name: 'proof_photo_required') bool proofPhotoRequired,@JsonKey(name: 'unreachable_available_at') DateTime? unreachableAvailableAt, JobActions actions
});


$JobPlaceCopyWith<$Res> get shop;$JobPlaceCopyWith<$Res> get customer;$JobActionsCopyWith<$Res> get actions;

}
/// @nodoc
class _$DriverJobCopyWithImpl<$Res>
    implements $DriverJobCopyWith<$Res> {
  _$DriverJobCopyWithImpl(this._self, this._then);

  final DriverJob _self;
  final $Res Function(DriverJob) _then;

/// Create a copy of DriverJob
/// with the given fields replaced by the non-null parameter values.
@pragma('vm:prefer-inline') @override $Res call({Object? id = null,Object? orderId = null,Object? orderCode = null,Object? status = null,Object? isActive = null,Object? distanceKm = freezed,Object? timeline = null,Object? cancelledBy = freezed,Object? cancelReason = freezed,Object? failureReason = freezed,Object? createdAt = freezed,Object? fee = null,Object? commission = null,Object? driverEarnings = null,Object? earnedAmount = freezed,Object? paymentStatus = freezed,Object? collectCash = null,Object? shop = null,Object? customer = null,Object? items = null,Object? notes = freezed,Object? callAttempts = null,Object? pinAttemptsLeft = null,Object? pinLocked = null,Object? proofPhotoRequired = null,Object? unreachableAvailableAt = freezed,Object? actions = null,}) {
  return _then(_self.copyWith(
id: null == id ? _self.id : id // ignore: cast_nullable_to_non_nullable
as int,orderId: null == orderId ? _self.orderId : orderId // ignore: cast_nullable_to_non_nullable
as int,orderCode: null == orderCode ? _self.orderCode : orderCode // ignore: cast_nullable_to_non_nullable
as String,status: null == status ? _self.status : status // ignore: cast_nullable_to_non_nullable
as String,isActive: null == isActive ? _self.isActive : isActive // ignore: cast_nullable_to_non_nullable
as bool,distanceKm: freezed == distanceKm ? _self.distanceKm : distanceKm // ignore: cast_nullable_to_non_nullable
as double?,timeline: null == timeline ? _self.timeline : timeline // ignore: cast_nullable_to_non_nullable
as List<TimelineEntry>,cancelledBy: freezed == cancelledBy ? _self.cancelledBy : cancelledBy // ignore: cast_nullable_to_non_nullable
as String?,cancelReason: freezed == cancelReason ? _self.cancelReason : cancelReason // ignore: cast_nullable_to_non_nullable
as String?,failureReason: freezed == failureReason ? _self.failureReason : failureReason // ignore: cast_nullable_to_non_nullable
as String?,createdAt: freezed == createdAt ? _self.createdAt : createdAt // ignore: cast_nullable_to_non_nullable
as DateTime?,fee: null == fee ? _self.fee : fee // ignore: cast_nullable_to_non_nullable
as int,commission: null == commission ? _self.commission : commission // ignore: cast_nullable_to_non_nullable
as int,driverEarnings: null == driverEarnings ? _self.driverEarnings : driverEarnings // ignore: cast_nullable_to_non_nullable
as int,earnedAmount: freezed == earnedAmount ? _self.earnedAmount : earnedAmount // ignore: cast_nullable_to_non_nullable
as int?,paymentStatus: freezed == paymentStatus ? _self.paymentStatus : paymentStatus // ignore: cast_nullable_to_non_nullable
as String?,collectCash: null == collectCash ? _self.collectCash : collectCash // ignore: cast_nullable_to_non_nullable
as int,shop: null == shop ? _self.shop : shop // ignore: cast_nullable_to_non_nullable
as JobPlace,customer: null == customer ? _self.customer : customer // ignore: cast_nullable_to_non_nullable
as JobPlace,items: null == items ? _self.items : items // ignore: cast_nullable_to_non_nullable
as List<JobItem>,notes: freezed == notes ? _self.notes : notes // ignore: cast_nullable_to_non_nullable
as String?,callAttempts: null == callAttempts ? _self.callAttempts : callAttempts // ignore: cast_nullable_to_non_nullable
as int,pinAttemptsLeft: null == pinAttemptsLeft ? _self.pinAttemptsLeft : pinAttemptsLeft // ignore: cast_nullable_to_non_nullable
as int,pinLocked: null == pinLocked ? _self.pinLocked : pinLocked // ignore: cast_nullable_to_non_nullable
as bool,proofPhotoRequired: null == proofPhotoRequired ? _self.proofPhotoRequired : proofPhotoRequired // ignore: cast_nullable_to_non_nullable
as bool,unreachableAvailableAt: freezed == unreachableAvailableAt ? _self.unreachableAvailableAt : unreachableAvailableAt // ignore: cast_nullable_to_non_nullable
as DateTime?,actions: null == actions ? _self.actions : actions // ignore: cast_nullable_to_non_nullable
as JobActions,
  ));
}
/// Create a copy of DriverJob
/// with the given fields replaced by the non-null parameter values.
@override
@pragma('vm:prefer-inline')
$JobPlaceCopyWith<$Res> get shop {
  
  return $JobPlaceCopyWith<$Res>(_self.shop, (value) {
    return _then(_self.copyWith(shop: value));
  });
}/// Create a copy of DriverJob
/// with the given fields replaced by the non-null parameter values.
@override
@pragma('vm:prefer-inline')
$JobPlaceCopyWith<$Res> get customer {
  
  return $JobPlaceCopyWith<$Res>(_self.customer, (value) {
    return _then(_self.copyWith(customer: value));
  });
}/// Create a copy of DriverJob
/// with the given fields replaced by the non-null parameter values.
@override
@pragma('vm:prefer-inline')
$JobActionsCopyWith<$Res> get actions {
  
  return $JobActionsCopyWith<$Res>(_self.actions, (value) {
    return _then(_self.copyWith(actions: value));
  });
}
}


/// Adds pattern-matching-related methods to [DriverJob].
extension DriverJobPatterns on DriverJob {
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

@optionalTypeArgs TResult maybeMap<TResult extends Object?>(TResult Function( _DriverJob value)?  $default,{required TResult orElse(),}){
final _that = this;
switch (_that) {
case _DriverJob() when $default != null:
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

@optionalTypeArgs TResult map<TResult extends Object?>(TResult Function( _DriverJob value)  $default,){
final _that = this;
switch (_that) {
case _DriverJob():
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

@optionalTypeArgs TResult? mapOrNull<TResult extends Object?>(TResult? Function( _DriverJob value)?  $default,){
final _that = this;
switch (_that) {
case _DriverJob() when $default != null:
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

@optionalTypeArgs TResult maybeWhen<TResult extends Object?>(TResult Function( int id, @JsonKey(name: 'order_id')  int orderId, @JsonKey(name: 'order_code')  String orderCode,  String status, @JsonKey(name: 'is_active')  bool isActive, @JsonKey(name: 'distance_km')  double? distanceKm,  List<TimelineEntry> timeline, @JsonKey(name: 'cancelled_by')  String? cancelledBy, @JsonKey(name: 'cancel_reason')  String? cancelReason, @JsonKey(name: 'failure_reason')  String? failureReason, @JsonKey(name: 'created_at')  DateTime? createdAt,  int fee,  int commission, @JsonKey(name: 'driver_earnings')  int driverEarnings, @JsonKey(name: 'earned_amount')  int? earnedAmount, @JsonKey(name: 'payment_status')  String? paymentStatus, @JsonKey(name: 'collect_cash')  int collectCash,  JobPlace shop,  JobPlace customer,  List<JobItem> items,  String? notes, @JsonKey(name: 'call_attempts')  int callAttempts, @JsonKey(name: 'pin_attempts_left')  int pinAttemptsLeft, @JsonKey(name: 'pin_locked')  bool pinLocked, @JsonKey(name: 'proof_photo_required')  bool proofPhotoRequired, @JsonKey(name: 'unreachable_available_at')  DateTime? unreachableAvailableAt,  JobActions actions)?  $default,{required TResult orElse(),}) {final _that = this;
switch (_that) {
case _DriverJob() when $default != null:
return $default(_that.id,_that.orderId,_that.orderCode,_that.status,_that.isActive,_that.distanceKm,_that.timeline,_that.cancelledBy,_that.cancelReason,_that.failureReason,_that.createdAt,_that.fee,_that.commission,_that.driverEarnings,_that.earnedAmount,_that.paymentStatus,_that.collectCash,_that.shop,_that.customer,_that.items,_that.notes,_that.callAttempts,_that.pinAttemptsLeft,_that.pinLocked,_that.proofPhotoRequired,_that.unreachableAvailableAt,_that.actions);case _:
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

@optionalTypeArgs TResult when<TResult extends Object?>(TResult Function( int id, @JsonKey(name: 'order_id')  int orderId, @JsonKey(name: 'order_code')  String orderCode,  String status, @JsonKey(name: 'is_active')  bool isActive, @JsonKey(name: 'distance_km')  double? distanceKm,  List<TimelineEntry> timeline, @JsonKey(name: 'cancelled_by')  String? cancelledBy, @JsonKey(name: 'cancel_reason')  String? cancelReason, @JsonKey(name: 'failure_reason')  String? failureReason, @JsonKey(name: 'created_at')  DateTime? createdAt,  int fee,  int commission, @JsonKey(name: 'driver_earnings')  int driverEarnings, @JsonKey(name: 'earned_amount')  int? earnedAmount, @JsonKey(name: 'payment_status')  String? paymentStatus, @JsonKey(name: 'collect_cash')  int collectCash,  JobPlace shop,  JobPlace customer,  List<JobItem> items,  String? notes, @JsonKey(name: 'call_attempts')  int callAttempts, @JsonKey(name: 'pin_attempts_left')  int pinAttemptsLeft, @JsonKey(name: 'pin_locked')  bool pinLocked, @JsonKey(name: 'proof_photo_required')  bool proofPhotoRequired, @JsonKey(name: 'unreachable_available_at')  DateTime? unreachableAvailableAt,  JobActions actions)  $default,) {final _that = this;
switch (_that) {
case _DriverJob():
return $default(_that.id,_that.orderId,_that.orderCode,_that.status,_that.isActive,_that.distanceKm,_that.timeline,_that.cancelledBy,_that.cancelReason,_that.failureReason,_that.createdAt,_that.fee,_that.commission,_that.driverEarnings,_that.earnedAmount,_that.paymentStatus,_that.collectCash,_that.shop,_that.customer,_that.items,_that.notes,_that.callAttempts,_that.pinAttemptsLeft,_that.pinLocked,_that.proofPhotoRequired,_that.unreachableAvailableAt,_that.actions);case _:
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

@optionalTypeArgs TResult? whenOrNull<TResult extends Object?>(TResult? Function( int id, @JsonKey(name: 'order_id')  int orderId, @JsonKey(name: 'order_code')  String orderCode,  String status, @JsonKey(name: 'is_active')  bool isActive, @JsonKey(name: 'distance_km')  double? distanceKm,  List<TimelineEntry> timeline, @JsonKey(name: 'cancelled_by')  String? cancelledBy, @JsonKey(name: 'cancel_reason')  String? cancelReason, @JsonKey(name: 'failure_reason')  String? failureReason, @JsonKey(name: 'created_at')  DateTime? createdAt,  int fee,  int commission, @JsonKey(name: 'driver_earnings')  int driverEarnings, @JsonKey(name: 'earned_amount')  int? earnedAmount, @JsonKey(name: 'payment_status')  String? paymentStatus, @JsonKey(name: 'collect_cash')  int collectCash,  JobPlace shop,  JobPlace customer,  List<JobItem> items,  String? notes, @JsonKey(name: 'call_attempts')  int callAttempts, @JsonKey(name: 'pin_attempts_left')  int pinAttemptsLeft, @JsonKey(name: 'pin_locked')  bool pinLocked, @JsonKey(name: 'proof_photo_required')  bool proofPhotoRequired, @JsonKey(name: 'unreachable_available_at')  DateTime? unreachableAvailableAt,  JobActions actions)?  $default,) {final _that = this;
switch (_that) {
case _DriverJob() when $default != null:
return $default(_that.id,_that.orderId,_that.orderCode,_that.status,_that.isActive,_that.distanceKm,_that.timeline,_that.cancelledBy,_that.cancelReason,_that.failureReason,_that.createdAt,_that.fee,_that.commission,_that.driverEarnings,_that.earnedAmount,_that.paymentStatus,_that.collectCash,_that.shop,_that.customer,_that.items,_that.notes,_that.callAttempts,_that.pinAttemptsLeft,_that.pinLocked,_that.proofPhotoRequired,_that.unreachableAvailableAt,_that.actions);case _:
  return null;

}
}

}

/// @nodoc
@JsonSerializable()

class _DriverJob extends DriverJob {
  const _DriverJob({required this.id, @JsonKey(name: 'order_id') required this.orderId, @JsonKey(name: 'order_code') required this.orderCode, required this.status, @JsonKey(name: 'is_active') this.isActive = false, @JsonKey(name: 'distance_km') this.distanceKm, final  List<TimelineEntry> timeline = const <TimelineEntry>[], @JsonKey(name: 'cancelled_by') this.cancelledBy, @JsonKey(name: 'cancel_reason') this.cancelReason, @JsonKey(name: 'failure_reason') this.failureReason, @JsonKey(name: 'created_at') this.createdAt, this.fee = 0, this.commission = 0, @JsonKey(name: 'driver_earnings') this.driverEarnings = 0, @JsonKey(name: 'earned_amount') this.earnedAmount, @JsonKey(name: 'payment_status') this.paymentStatus, @JsonKey(name: 'collect_cash') this.collectCash = 0, required this.shop, required this.customer, final  List<JobItem> items = const <JobItem>[], this.notes, @JsonKey(name: 'call_attempts') this.callAttempts = 0, @JsonKey(name: 'pin_attempts_left') this.pinAttemptsLeft = 5, @JsonKey(name: 'pin_locked') this.pinLocked = false, @JsonKey(name: 'proof_photo_required') this.proofPhotoRequired = false, @JsonKey(name: 'unreachable_available_at') this.unreachableAvailableAt, this.actions = const JobActions()}): _timeline = timeline,_items = items,super._();
  factory _DriverJob.fromJson(Map<String, dynamic> json) => _$DriverJobFromJson(json);

@override final  int id;
@override@JsonKey(name: 'order_id') final  int orderId;
/// Masked (`SKAB12••••`) until pickup: its last 4 characters are the
/// pickup code the seller gives the driver.
@override@JsonKey(name: 'order_code') final  String orderCode;
@override final  String status;
@override@JsonKey(name: 'is_active') final  bool isActive;
@override@JsonKey(name: 'distance_km') final  double? distanceKm;
 final  List<TimelineEntry> _timeline;
@override@JsonKey() List<TimelineEntry> get timeline {
  if (_timeline is EqualUnmodifiableListView) return _timeline;
  // ignore: implicit_dynamic_type
  return EqualUnmodifiableListView(_timeline);
}

@override@JsonKey(name: 'cancelled_by') final  String? cancelledBy;
@override@JsonKey(name: 'cancel_reason') final  String? cancelReason;
@override@JsonKey(name: 'failure_reason') final  String? failureReason;
@override@JsonKey(name: 'created_at') final  DateTime? createdAt;
@override@JsonKey() final  int fee;
@override@JsonKey() final  int commission;
@override@JsonKey(name: 'driver_earnings') final  int driverEarnings;
@override@JsonKey(name: 'earned_amount') final  int? earnedAmount;
@override@JsonKey(name: 'payment_status') final  String? paymentStatus;
@override@JsonKey(name: 'collect_cash') final  int collectCash;
@override final  JobPlace shop;
@override final  JobPlace customer;
 final  List<JobItem> _items;
@override@JsonKey() List<JobItem> get items {
  if (_items is EqualUnmodifiableListView) return _items;
  // ignore: implicit_dynamic_type
  return EqualUnmodifiableListView(_items);
}

@override final  String? notes;
@override@JsonKey(name: 'call_attempts') final  int callAttempts;
@override@JsonKey(name: 'pin_attempts_left') final  int pinAttemptsLeft;
@override@JsonKey(name: 'pin_locked') final  bool pinLocked;
@override@JsonKey(name: 'proof_photo_required') final  bool proofPhotoRequired;
@override@JsonKey(name: 'unreachable_available_at') final  DateTime? unreachableAvailableAt;
@override@JsonKey() final  JobActions actions;

/// Create a copy of DriverJob
/// with the given fields replaced by the non-null parameter values.
@override @JsonKey(includeFromJson: false, includeToJson: false)
@pragma('vm:prefer-inline')
_$DriverJobCopyWith<_DriverJob> get copyWith => __$DriverJobCopyWithImpl<_DriverJob>(this, _$identity);

@override
Map<String, dynamic> toJson() {
  return _$DriverJobToJson(this, );
}

@override
bool operator ==(Object other) {
  return identical(this, other) || (other.runtimeType == runtimeType&&other is _DriverJob&&(identical(other.id, id) || other.id == id)&&(identical(other.orderId, orderId) || other.orderId == orderId)&&(identical(other.orderCode, orderCode) || other.orderCode == orderCode)&&(identical(other.status, status) || other.status == status)&&(identical(other.isActive, isActive) || other.isActive == isActive)&&(identical(other.distanceKm, distanceKm) || other.distanceKm == distanceKm)&&const DeepCollectionEquality().equals(other._timeline, _timeline)&&(identical(other.cancelledBy, cancelledBy) || other.cancelledBy == cancelledBy)&&(identical(other.cancelReason, cancelReason) || other.cancelReason == cancelReason)&&(identical(other.failureReason, failureReason) || other.failureReason == failureReason)&&(identical(other.createdAt, createdAt) || other.createdAt == createdAt)&&(identical(other.fee, fee) || other.fee == fee)&&(identical(other.commission, commission) || other.commission == commission)&&(identical(other.driverEarnings, driverEarnings) || other.driverEarnings == driverEarnings)&&(identical(other.earnedAmount, earnedAmount) || other.earnedAmount == earnedAmount)&&(identical(other.paymentStatus, paymentStatus) || other.paymentStatus == paymentStatus)&&(identical(other.collectCash, collectCash) || other.collectCash == collectCash)&&(identical(other.shop, shop) || other.shop == shop)&&(identical(other.customer, customer) || other.customer == customer)&&const DeepCollectionEquality().equals(other._items, _items)&&(identical(other.notes, notes) || other.notes == notes)&&(identical(other.callAttempts, callAttempts) || other.callAttempts == callAttempts)&&(identical(other.pinAttemptsLeft, pinAttemptsLeft) || other.pinAttemptsLeft == pinAttemptsLeft)&&(identical(other.pinLocked, pinLocked) || other.pinLocked == pinLocked)&&(identical(other.proofPhotoRequired, proofPhotoRequired) || other.proofPhotoRequired == proofPhotoRequired)&&(identical(other.unreachableAvailableAt, unreachableAvailableAt) || other.unreachableAvailableAt == unreachableAvailableAt)&&(identical(other.actions, actions) || other.actions == actions));
}

@JsonKey(includeFromJson: false, includeToJson: false)
@override
int get hashCode => Object.hashAll([runtimeType,id,orderId,orderCode,status,isActive,distanceKm,const DeepCollectionEquality().hash(_timeline),cancelledBy,cancelReason,failureReason,createdAt,fee,commission,driverEarnings,earnedAmount,paymentStatus,collectCash,shop,customer,const DeepCollectionEquality().hash(_items),notes,callAttempts,pinAttemptsLeft,pinLocked,proofPhotoRequired,unreachableAvailableAt,actions]);

@override
String toString() {
  return 'DriverJob(id: $id, orderId: $orderId, orderCode: $orderCode, status: $status, isActive: $isActive, distanceKm: $distanceKm, timeline: $timeline, cancelledBy: $cancelledBy, cancelReason: $cancelReason, failureReason: $failureReason, createdAt: $createdAt, fee: $fee, commission: $commission, driverEarnings: $driverEarnings, earnedAmount: $earnedAmount, paymentStatus: $paymentStatus, collectCash: $collectCash, shop: $shop, customer: $customer, items: $items, notes: $notes, callAttempts: $callAttempts, pinAttemptsLeft: $pinAttemptsLeft, pinLocked: $pinLocked, proofPhotoRequired: $proofPhotoRequired, unreachableAvailableAt: $unreachableAvailableAt, actions: $actions)';
}


}

/// @nodoc
abstract mixin class _$DriverJobCopyWith<$Res> implements $DriverJobCopyWith<$Res> {
  factory _$DriverJobCopyWith(_DriverJob value, $Res Function(_DriverJob) _then) = __$DriverJobCopyWithImpl;
@override @useResult
$Res call({
 int id,@JsonKey(name: 'order_id') int orderId,@JsonKey(name: 'order_code') String orderCode, String status,@JsonKey(name: 'is_active') bool isActive,@JsonKey(name: 'distance_km') double? distanceKm, List<TimelineEntry> timeline,@JsonKey(name: 'cancelled_by') String? cancelledBy,@JsonKey(name: 'cancel_reason') String? cancelReason,@JsonKey(name: 'failure_reason') String? failureReason,@JsonKey(name: 'created_at') DateTime? createdAt, int fee, int commission,@JsonKey(name: 'driver_earnings') int driverEarnings,@JsonKey(name: 'earned_amount') int? earnedAmount,@JsonKey(name: 'payment_status') String? paymentStatus,@JsonKey(name: 'collect_cash') int collectCash, JobPlace shop, JobPlace customer, List<JobItem> items, String? notes,@JsonKey(name: 'call_attempts') int callAttempts,@JsonKey(name: 'pin_attempts_left') int pinAttemptsLeft,@JsonKey(name: 'pin_locked') bool pinLocked,@JsonKey(name: 'proof_photo_required') bool proofPhotoRequired,@JsonKey(name: 'unreachable_available_at') DateTime? unreachableAvailableAt, JobActions actions
});


@override $JobPlaceCopyWith<$Res> get shop;@override $JobPlaceCopyWith<$Res> get customer;@override $JobActionsCopyWith<$Res> get actions;

}
/// @nodoc
class __$DriverJobCopyWithImpl<$Res>
    implements _$DriverJobCopyWith<$Res> {
  __$DriverJobCopyWithImpl(this._self, this._then);

  final _DriverJob _self;
  final $Res Function(_DriverJob) _then;

/// Create a copy of DriverJob
/// with the given fields replaced by the non-null parameter values.
@override @pragma('vm:prefer-inline') $Res call({Object? id = null,Object? orderId = null,Object? orderCode = null,Object? status = null,Object? isActive = null,Object? distanceKm = freezed,Object? timeline = null,Object? cancelledBy = freezed,Object? cancelReason = freezed,Object? failureReason = freezed,Object? createdAt = freezed,Object? fee = null,Object? commission = null,Object? driverEarnings = null,Object? earnedAmount = freezed,Object? paymentStatus = freezed,Object? collectCash = null,Object? shop = null,Object? customer = null,Object? items = null,Object? notes = freezed,Object? callAttempts = null,Object? pinAttemptsLeft = null,Object? pinLocked = null,Object? proofPhotoRequired = null,Object? unreachableAvailableAt = freezed,Object? actions = null,}) {
  return _then(_DriverJob(
id: null == id ? _self.id : id // ignore: cast_nullable_to_non_nullable
as int,orderId: null == orderId ? _self.orderId : orderId // ignore: cast_nullable_to_non_nullable
as int,orderCode: null == orderCode ? _self.orderCode : orderCode // ignore: cast_nullable_to_non_nullable
as String,status: null == status ? _self.status : status // ignore: cast_nullable_to_non_nullable
as String,isActive: null == isActive ? _self.isActive : isActive // ignore: cast_nullable_to_non_nullable
as bool,distanceKm: freezed == distanceKm ? _self.distanceKm : distanceKm // ignore: cast_nullable_to_non_nullable
as double?,timeline: null == timeline ? _self._timeline : timeline // ignore: cast_nullable_to_non_nullable
as List<TimelineEntry>,cancelledBy: freezed == cancelledBy ? _self.cancelledBy : cancelledBy // ignore: cast_nullable_to_non_nullable
as String?,cancelReason: freezed == cancelReason ? _self.cancelReason : cancelReason // ignore: cast_nullable_to_non_nullable
as String?,failureReason: freezed == failureReason ? _self.failureReason : failureReason // ignore: cast_nullable_to_non_nullable
as String?,createdAt: freezed == createdAt ? _self.createdAt : createdAt // ignore: cast_nullable_to_non_nullable
as DateTime?,fee: null == fee ? _self.fee : fee // ignore: cast_nullable_to_non_nullable
as int,commission: null == commission ? _self.commission : commission // ignore: cast_nullable_to_non_nullable
as int,driverEarnings: null == driverEarnings ? _self.driverEarnings : driverEarnings // ignore: cast_nullable_to_non_nullable
as int,earnedAmount: freezed == earnedAmount ? _self.earnedAmount : earnedAmount // ignore: cast_nullable_to_non_nullable
as int?,paymentStatus: freezed == paymentStatus ? _self.paymentStatus : paymentStatus // ignore: cast_nullable_to_non_nullable
as String?,collectCash: null == collectCash ? _self.collectCash : collectCash // ignore: cast_nullable_to_non_nullable
as int,shop: null == shop ? _self.shop : shop // ignore: cast_nullable_to_non_nullable
as JobPlace,customer: null == customer ? _self.customer : customer // ignore: cast_nullable_to_non_nullable
as JobPlace,items: null == items ? _self._items : items // ignore: cast_nullable_to_non_nullable
as List<JobItem>,notes: freezed == notes ? _self.notes : notes // ignore: cast_nullable_to_non_nullable
as String?,callAttempts: null == callAttempts ? _self.callAttempts : callAttempts // ignore: cast_nullable_to_non_nullable
as int,pinAttemptsLeft: null == pinAttemptsLeft ? _self.pinAttemptsLeft : pinAttemptsLeft // ignore: cast_nullable_to_non_nullable
as int,pinLocked: null == pinLocked ? _self.pinLocked : pinLocked // ignore: cast_nullable_to_non_nullable
as bool,proofPhotoRequired: null == proofPhotoRequired ? _self.proofPhotoRequired : proofPhotoRequired // ignore: cast_nullable_to_non_nullable
as bool,unreachableAvailableAt: freezed == unreachableAvailableAt ? _self.unreachableAvailableAt : unreachableAvailableAt // ignore: cast_nullable_to_non_nullable
as DateTime?,actions: null == actions ? _self.actions : actions // ignore: cast_nullable_to_non_nullable
as JobActions,
  ));
}

/// Create a copy of DriverJob
/// with the given fields replaced by the non-null parameter values.
@override
@pragma('vm:prefer-inline')
$JobPlaceCopyWith<$Res> get shop {
  
  return $JobPlaceCopyWith<$Res>(_self.shop, (value) {
    return _then(_self.copyWith(shop: value));
  });
}/// Create a copy of DriverJob
/// with the given fields replaced by the non-null parameter values.
@override
@pragma('vm:prefer-inline')
$JobPlaceCopyWith<$Res> get customer {
  
  return $JobPlaceCopyWith<$Res>(_self.customer, (value) {
    return _then(_self.copyWith(customer: value));
  });
}/// Create a copy of DriverJob
/// with the given fields replaced by the non-null parameter values.
@override
@pragma('vm:prefer-inline')
$JobActionsCopyWith<$Res> get actions {
  
  return $JobActionsCopyWith<$Res>(_self.actions, (value) {
    return _then(_self.copyWith(actions: value));
  });
}
}


/// @nodoc
mixin _$JobPlace {

 String? get name; String? get address; String? get phone; double get lat; double get lng;
/// Create a copy of JobPlace
/// with the given fields replaced by the non-null parameter values.
@JsonKey(includeFromJson: false, includeToJson: false)
@pragma('vm:prefer-inline')
$JobPlaceCopyWith<JobPlace> get copyWith => _$JobPlaceCopyWithImpl<JobPlace>(this as JobPlace, _$identity);

  /// Serializes this JobPlace to a JSON map.
  Map<String, dynamic> toJson();


@override
bool operator ==(Object other) {
  return identical(this, other) || (other.runtimeType == runtimeType&&other is JobPlace&&(identical(other.name, name) || other.name == name)&&(identical(other.address, address) || other.address == address)&&(identical(other.phone, phone) || other.phone == phone)&&(identical(other.lat, lat) || other.lat == lat)&&(identical(other.lng, lng) || other.lng == lng));
}

@JsonKey(includeFromJson: false, includeToJson: false)
@override
int get hashCode => Object.hash(runtimeType,name,address,phone,lat,lng);

@override
String toString() {
  return 'JobPlace(name: $name, address: $address, phone: $phone, lat: $lat, lng: $lng)';
}


}

/// @nodoc
abstract mixin class $JobPlaceCopyWith<$Res>  {
  factory $JobPlaceCopyWith(JobPlace value, $Res Function(JobPlace) _then) = _$JobPlaceCopyWithImpl;
@useResult
$Res call({
 String? name, String? address, String? phone, double lat, double lng
});




}
/// @nodoc
class _$JobPlaceCopyWithImpl<$Res>
    implements $JobPlaceCopyWith<$Res> {
  _$JobPlaceCopyWithImpl(this._self, this._then);

  final JobPlace _self;
  final $Res Function(JobPlace) _then;

/// Create a copy of JobPlace
/// with the given fields replaced by the non-null parameter values.
@pragma('vm:prefer-inline') @override $Res call({Object? name = freezed,Object? address = freezed,Object? phone = freezed,Object? lat = null,Object? lng = null,}) {
  return _then(_self.copyWith(
name: freezed == name ? _self.name : name // ignore: cast_nullable_to_non_nullable
as String?,address: freezed == address ? _self.address : address // ignore: cast_nullable_to_non_nullable
as String?,phone: freezed == phone ? _self.phone : phone // ignore: cast_nullable_to_non_nullable
as String?,lat: null == lat ? _self.lat : lat // ignore: cast_nullable_to_non_nullable
as double,lng: null == lng ? _self.lng : lng // ignore: cast_nullable_to_non_nullable
as double,
  ));
}

}


/// Adds pattern-matching-related methods to [JobPlace].
extension JobPlacePatterns on JobPlace {
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

@optionalTypeArgs TResult maybeMap<TResult extends Object?>(TResult Function( _JobPlace value)?  $default,{required TResult orElse(),}){
final _that = this;
switch (_that) {
case _JobPlace() when $default != null:
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

@optionalTypeArgs TResult map<TResult extends Object?>(TResult Function( _JobPlace value)  $default,){
final _that = this;
switch (_that) {
case _JobPlace():
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

@optionalTypeArgs TResult? mapOrNull<TResult extends Object?>(TResult? Function( _JobPlace value)?  $default,){
final _that = this;
switch (_that) {
case _JobPlace() when $default != null:
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

@optionalTypeArgs TResult maybeWhen<TResult extends Object?>(TResult Function( String? name,  String? address,  String? phone,  double lat,  double lng)?  $default,{required TResult orElse(),}) {final _that = this;
switch (_that) {
case _JobPlace() when $default != null:
return $default(_that.name,_that.address,_that.phone,_that.lat,_that.lng);case _:
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

@optionalTypeArgs TResult when<TResult extends Object?>(TResult Function( String? name,  String? address,  String? phone,  double lat,  double lng)  $default,) {final _that = this;
switch (_that) {
case _JobPlace():
return $default(_that.name,_that.address,_that.phone,_that.lat,_that.lng);case _:
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

@optionalTypeArgs TResult? whenOrNull<TResult extends Object?>(TResult? Function( String? name,  String? address,  String? phone,  double lat,  double lng)?  $default,) {final _that = this;
switch (_that) {
case _JobPlace() when $default != null:
return $default(_that.name,_that.address,_that.phone,_that.lat,_that.lng);case _:
  return null;

}
}

}

/// @nodoc
@JsonSerializable()

class _JobPlace implements JobPlace {
  const _JobPlace({this.name, this.address, this.phone, required this.lat, required this.lng});
  factory _JobPlace.fromJson(Map<String, dynamic> json) => _$JobPlaceFromJson(json);

@override final  String? name;
@override final  String? address;
@override final  String? phone;
@override final  double lat;
@override final  double lng;

/// Create a copy of JobPlace
/// with the given fields replaced by the non-null parameter values.
@override @JsonKey(includeFromJson: false, includeToJson: false)
@pragma('vm:prefer-inline')
_$JobPlaceCopyWith<_JobPlace> get copyWith => __$JobPlaceCopyWithImpl<_JobPlace>(this, _$identity);

@override
Map<String, dynamic> toJson() {
  return _$JobPlaceToJson(this, );
}

@override
bool operator ==(Object other) {
  return identical(this, other) || (other.runtimeType == runtimeType&&other is _JobPlace&&(identical(other.name, name) || other.name == name)&&(identical(other.address, address) || other.address == address)&&(identical(other.phone, phone) || other.phone == phone)&&(identical(other.lat, lat) || other.lat == lat)&&(identical(other.lng, lng) || other.lng == lng));
}

@JsonKey(includeFromJson: false, includeToJson: false)
@override
int get hashCode => Object.hash(runtimeType,name,address,phone,lat,lng);

@override
String toString() {
  return 'JobPlace(name: $name, address: $address, phone: $phone, lat: $lat, lng: $lng)';
}


}

/// @nodoc
abstract mixin class _$JobPlaceCopyWith<$Res> implements $JobPlaceCopyWith<$Res> {
  factory _$JobPlaceCopyWith(_JobPlace value, $Res Function(_JobPlace) _then) = __$JobPlaceCopyWithImpl;
@override @useResult
$Res call({
 String? name, String? address, String? phone, double lat, double lng
});




}
/// @nodoc
class __$JobPlaceCopyWithImpl<$Res>
    implements _$JobPlaceCopyWith<$Res> {
  __$JobPlaceCopyWithImpl(this._self, this._then);

  final _JobPlace _self;
  final $Res Function(_JobPlace) _then;

/// Create a copy of JobPlace
/// with the given fields replaced by the non-null parameter values.
@override @pragma('vm:prefer-inline') $Res call({Object? name = freezed,Object? address = freezed,Object? phone = freezed,Object? lat = null,Object? lng = null,}) {
  return _then(_JobPlace(
name: freezed == name ? _self.name : name // ignore: cast_nullable_to_non_nullable
as String?,address: freezed == address ? _self.address : address // ignore: cast_nullable_to_non_nullable
as String?,phone: freezed == phone ? _self.phone : phone // ignore: cast_nullable_to_non_nullable
as String?,lat: null == lat ? _self.lat : lat // ignore: cast_nullable_to_non_nullable
as double,lng: null == lng ? _self.lng : lng // ignore: cast_nullable_to_non_nullable
as double,
  ));
}


}


/// @nodoc
mixin _$JobItem {

 String get title; int get qty;
/// Create a copy of JobItem
/// with the given fields replaced by the non-null parameter values.
@JsonKey(includeFromJson: false, includeToJson: false)
@pragma('vm:prefer-inline')
$JobItemCopyWith<JobItem> get copyWith => _$JobItemCopyWithImpl<JobItem>(this as JobItem, _$identity);

  /// Serializes this JobItem to a JSON map.
  Map<String, dynamic> toJson();


@override
bool operator ==(Object other) {
  return identical(this, other) || (other.runtimeType == runtimeType&&other is JobItem&&(identical(other.title, title) || other.title == title)&&(identical(other.qty, qty) || other.qty == qty));
}

@JsonKey(includeFromJson: false, includeToJson: false)
@override
int get hashCode => Object.hash(runtimeType,title,qty);

@override
String toString() {
  return 'JobItem(title: $title, qty: $qty)';
}


}

/// @nodoc
abstract mixin class $JobItemCopyWith<$Res>  {
  factory $JobItemCopyWith(JobItem value, $Res Function(JobItem) _then) = _$JobItemCopyWithImpl;
@useResult
$Res call({
 String title, int qty
});




}
/// @nodoc
class _$JobItemCopyWithImpl<$Res>
    implements $JobItemCopyWith<$Res> {
  _$JobItemCopyWithImpl(this._self, this._then);

  final JobItem _self;
  final $Res Function(JobItem) _then;

/// Create a copy of JobItem
/// with the given fields replaced by the non-null parameter values.
@pragma('vm:prefer-inline') @override $Res call({Object? title = null,Object? qty = null,}) {
  return _then(_self.copyWith(
title: null == title ? _self.title : title // ignore: cast_nullable_to_non_nullable
as String,qty: null == qty ? _self.qty : qty // ignore: cast_nullable_to_non_nullable
as int,
  ));
}

}


/// Adds pattern-matching-related methods to [JobItem].
extension JobItemPatterns on JobItem {
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

@optionalTypeArgs TResult maybeMap<TResult extends Object?>(TResult Function( _JobItem value)?  $default,{required TResult orElse(),}){
final _that = this;
switch (_that) {
case _JobItem() when $default != null:
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

@optionalTypeArgs TResult map<TResult extends Object?>(TResult Function( _JobItem value)  $default,){
final _that = this;
switch (_that) {
case _JobItem():
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

@optionalTypeArgs TResult? mapOrNull<TResult extends Object?>(TResult? Function( _JobItem value)?  $default,){
final _that = this;
switch (_that) {
case _JobItem() when $default != null:
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

@optionalTypeArgs TResult maybeWhen<TResult extends Object?>(TResult Function( String title,  int qty)?  $default,{required TResult orElse(),}) {final _that = this;
switch (_that) {
case _JobItem() when $default != null:
return $default(_that.title,_that.qty);case _:
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

@optionalTypeArgs TResult when<TResult extends Object?>(TResult Function( String title,  int qty)  $default,) {final _that = this;
switch (_that) {
case _JobItem():
return $default(_that.title,_that.qty);case _:
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

@optionalTypeArgs TResult? whenOrNull<TResult extends Object?>(TResult? Function( String title,  int qty)?  $default,) {final _that = this;
switch (_that) {
case _JobItem() when $default != null:
return $default(_that.title,_that.qty);case _:
  return null;

}
}

}

/// @nodoc
@JsonSerializable()

class _JobItem implements JobItem {
  const _JobItem({required this.title, this.qty = 1});
  factory _JobItem.fromJson(Map<String, dynamic> json) => _$JobItemFromJson(json);

@override final  String title;
@override@JsonKey() final  int qty;

/// Create a copy of JobItem
/// with the given fields replaced by the non-null parameter values.
@override @JsonKey(includeFromJson: false, includeToJson: false)
@pragma('vm:prefer-inline')
_$JobItemCopyWith<_JobItem> get copyWith => __$JobItemCopyWithImpl<_JobItem>(this, _$identity);

@override
Map<String, dynamic> toJson() {
  return _$JobItemToJson(this, );
}

@override
bool operator ==(Object other) {
  return identical(this, other) || (other.runtimeType == runtimeType&&other is _JobItem&&(identical(other.title, title) || other.title == title)&&(identical(other.qty, qty) || other.qty == qty));
}

@JsonKey(includeFromJson: false, includeToJson: false)
@override
int get hashCode => Object.hash(runtimeType,title,qty);

@override
String toString() {
  return 'JobItem(title: $title, qty: $qty)';
}


}

/// @nodoc
abstract mixin class _$JobItemCopyWith<$Res> implements $JobItemCopyWith<$Res> {
  factory _$JobItemCopyWith(_JobItem value, $Res Function(_JobItem) _then) = __$JobItemCopyWithImpl;
@override @useResult
$Res call({
 String title, int qty
});




}
/// @nodoc
class __$JobItemCopyWithImpl<$Res>
    implements _$JobItemCopyWith<$Res> {
  __$JobItemCopyWithImpl(this._self, this._then);

  final _JobItem _self;
  final $Res Function(_JobItem) _then;

/// Create a copy of JobItem
/// with the given fields replaced by the non-null parameter values.
@override @pragma('vm:prefer-inline') $Res call({Object? title = null,Object? qty = null,}) {
  return _then(_JobItem(
title: null == title ? _self.title : title // ignore: cast_nullable_to_non_nullable
as String,qty: null == qty ? _self.qty : qty // ignore: cast_nullable_to_non_nullable
as int,
  ));
}


}


/// @nodoc
mixin _$TimelineEntry {

 String? get status; DateTime? get at; String? get by; String? get reason;
/// Create a copy of TimelineEntry
/// with the given fields replaced by the non-null parameter values.
@JsonKey(includeFromJson: false, includeToJson: false)
@pragma('vm:prefer-inline')
$TimelineEntryCopyWith<TimelineEntry> get copyWith => _$TimelineEntryCopyWithImpl<TimelineEntry>(this as TimelineEntry, _$identity);

  /// Serializes this TimelineEntry to a JSON map.
  Map<String, dynamic> toJson();


@override
bool operator ==(Object other) {
  return identical(this, other) || (other.runtimeType == runtimeType&&other is TimelineEntry&&(identical(other.status, status) || other.status == status)&&(identical(other.at, at) || other.at == at)&&(identical(other.by, by) || other.by == by)&&(identical(other.reason, reason) || other.reason == reason));
}

@JsonKey(includeFromJson: false, includeToJson: false)
@override
int get hashCode => Object.hash(runtimeType,status,at,by,reason);

@override
String toString() {
  return 'TimelineEntry(status: $status, at: $at, by: $by, reason: $reason)';
}


}

/// @nodoc
abstract mixin class $TimelineEntryCopyWith<$Res>  {
  factory $TimelineEntryCopyWith(TimelineEntry value, $Res Function(TimelineEntry) _then) = _$TimelineEntryCopyWithImpl;
@useResult
$Res call({
 String? status, DateTime? at, String? by, String? reason
});




}
/// @nodoc
class _$TimelineEntryCopyWithImpl<$Res>
    implements $TimelineEntryCopyWith<$Res> {
  _$TimelineEntryCopyWithImpl(this._self, this._then);

  final TimelineEntry _self;
  final $Res Function(TimelineEntry) _then;

/// Create a copy of TimelineEntry
/// with the given fields replaced by the non-null parameter values.
@pragma('vm:prefer-inline') @override $Res call({Object? status = freezed,Object? at = freezed,Object? by = freezed,Object? reason = freezed,}) {
  return _then(_self.copyWith(
status: freezed == status ? _self.status : status // ignore: cast_nullable_to_non_nullable
as String?,at: freezed == at ? _self.at : at // ignore: cast_nullable_to_non_nullable
as DateTime?,by: freezed == by ? _self.by : by // ignore: cast_nullable_to_non_nullable
as String?,reason: freezed == reason ? _self.reason : reason // ignore: cast_nullable_to_non_nullable
as String?,
  ));
}

}


/// Adds pattern-matching-related methods to [TimelineEntry].
extension TimelineEntryPatterns on TimelineEntry {
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

@optionalTypeArgs TResult maybeMap<TResult extends Object?>(TResult Function( _TimelineEntry value)?  $default,{required TResult orElse(),}){
final _that = this;
switch (_that) {
case _TimelineEntry() when $default != null:
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

@optionalTypeArgs TResult map<TResult extends Object?>(TResult Function( _TimelineEntry value)  $default,){
final _that = this;
switch (_that) {
case _TimelineEntry():
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

@optionalTypeArgs TResult? mapOrNull<TResult extends Object?>(TResult? Function( _TimelineEntry value)?  $default,){
final _that = this;
switch (_that) {
case _TimelineEntry() when $default != null:
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

@optionalTypeArgs TResult maybeWhen<TResult extends Object?>(TResult Function( String? status,  DateTime? at,  String? by,  String? reason)?  $default,{required TResult orElse(),}) {final _that = this;
switch (_that) {
case _TimelineEntry() when $default != null:
return $default(_that.status,_that.at,_that.by,_that.reason);case _:
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

@optionalTypeArgs TResult when<TResult extends Object?>(TResult Function( String? status,  DateTime? at,  String? by,  String? reason)  $default,) {final _that = this;
switch (_that) {
case _TimelineEntry():
return $default(_that.status,_that.at,_that.by,_that.reason);case _:
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

@optionalTypeArgs TResult? whenOrNull<TResult extends Object?>(TResult? Function( String? status,  DateTime? at,  String? by,  String? reason)?  $default,) {final _that = this;
switch (_that) {
case _TimelineEntry() when $default != null:
return $default(_that.status,_that.at,_that.by,_that.reason);case _:
  return null;

}
}

}

/// @nodoc
@JsonSerializable()

class _TimelineEntry implements TimelineEntry {
  const _TimelineEntry({this.status, this.at, this.by, this.reason});
  factory _TimelineEntry.fromJson(Map<String, dynamic> json) => _$TimelineEntryFromJson(json);

@override final  String? status;
@override final  DateTime? at;
@override final  String? by;
@override final  String? reason;

/// Create a copy of TimelineEntry
/// with the given fields replaced by the non-null parameter values.
@override @JsonKey(includeFromJson: false, includeToJson: false)
@pragma('vm:prefer-inline')
_$TimelineEntryCopyWith<_TimelineEntry> get copyWith => __$TimelineEntryCopyWithImpl<_TimelineEntry>(this, _$identity);

@override
Map<String, dynamic> toJson() {
  return _$TimelineEntryToJson(this, );
}

@override
bool operator ==(Object other) {
  return identical(this, other) || (other.runtimeType == runtimeType&&other is _TimelineEntry&&(identical(other.status, status) || other.status == status)&&(identical(other.at, at) || other.at == at)&&(identical(other.by, by) || other.by == by)&&(identical(other.reason, reason) || other.reason == reason));
}

@JsonKey(includeFromJson: false, includeToJson: false)
@override
int get hashCode => Object.hash(runtimeType,status,at,by,reason);

@override
String toString() {
  return 'TimelineEntry(status: $status, at: $at, by: $by, reason: $reason)';
}


}

/// @nodoc
abstract mixin class _$TimelineEntryCopyWith<$Res> implements $TimelineEntryCopyWith<$Res> {
  factory _$TimelineEntryCopyWith(_TimelineEntry value, $Res Function(_TimelineEntry) _then) = __$TimelineEntryCopyWithImpl;
@override @useResult
$Res call({
 String? status, DateTime? at, String? by, String? reason
});




}
/// @nodoc
class __$TimelineEntryCopyWithImpl<$Res>
    implements _$TimelineEntryCopyWith<$Res> {
  __$TimelineEntryCopyWithImpl(this._self, this._then);

  final _TimelineEntry _self;
  final $Res Function(_TimelineEntry) _then;

/// Create a copy of TimelineEntry
/// with the given fields replaced by the non-null parameter values.
@override @pragma('vm:prefer-inline') $Res call({Object? status = freezed,Object? at = freezed,Object? by = freezed,Object? reason = freezed,}) {
  return _then(_TimelineEntry(
status: freezed == status ? _self.status : status // ignore: cast_nullable_to_non_nullable
as String?,at: freezed == at ? _self.at : at // ignore: cast_nullable_to_non_nullable
as DateTime?,by: freezed == by ? _self.by : by // ignore: cast_nullable_to_non_nullable
as String?,reason: freezed == reason ? _self.reason : reason // ignore: cast_nullable_to_non_nullable
as String?,
  ));
}


}


/// @nodoc
mixin _$JobActions {

 List<String> get next;@JsonKey(name: 'can_cancel') bool get canCancel;@JsonKey(name: 'can_rate') bool get canRate;
/// Create a copy of JobActions
/// with the given fields replaced by the non-null parameter values.
@JsonKey(includeFromJson: false, includeToJson: false)
@pragma('vm:prefer-inline')
$JobActionsCopyWith<JobActions> get copyWith => _$JobActionsCopyWithImpl<JobActions>(this as JobActions, _$identity);

  /// Serializes this JobActions to a JSON map.
  Map<String, dynamic> toJson();


@override
bool operator ==(Object other) {
  return identical(this, other) || (other.runtimeType == runtimeType&&other is JobActions&&const DeepCollectionEquality().equals(other.next, next)&&(identical(other.canCancel, canCancel) || other.canCancel == canCancel)&&(identical(other.canRate, canRate) || other.canRate == canRate));
}

@JsonKey(includeFromJson: false, includeToJson: false)
@override
int get hashCode => Object.hash(runtimeType,const DeepCollectionEquality().hash(next),canCancel,canRate);

@override
String toString() {
  return 'JobActions(next: $next, canCancel: $canCancel, canRate: $canRate)';
}


}

/// @nodoc
abstract mixin class $JobActionsCopyWith<$Res>  {
  factory $JobActionsCopyWith(JobActions value, $Res Function(JobActions) _then) = _$JobActionsCopyWithImpl;
@useResult
$Res call({
 List<String> next,@JsonKey(name: 'can_cancel') bool canCancel,@JsonKey(name: 'can_rate') bool canRate
});




}
/// @nodoc
class _$JobActionsCopyWithImpl<$Res>
    implements $JobActionsCopyWith<$Res> {
  _$JobActionsCopyWithImpl(this._self, this._then);

  final JobActions _self;
  final $Res Function(JobActions) _then;

/// Create a copy of JobActions
/// with the given fields replaced by the non-null parameter values.
@pragma('vm:prefer-inline') @override $Res call({Object? next = null,Object? canCancel = null,Object? canRate = null,}) {
  return _then(_self.copyWith(
next: null == next ? _self.next : next // ignore: cast_nullable_to_non_nullable
as List<String>,canCancel: null == canCancel ? _self.canCancel : canCancel // ignore: cast_nullable_to_non_nullable
as bool,canRate: null == canRate ? _self.canRate : canRate // ignore: cast_nullable_to_non_nullable
as bool,
  ));
}

}


/// Adds pattern-matching-related methods to [JobActions].
extension JobActionsPatterns on JobActions {
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

@optionalTypeArgs TResult maybeMap<TResult extends Object?>(TResult Function( _JobActions value)?  $default,{required TResult orElse(),}){
final _that = this;
switch (_that) {
case _JobActions() when $default != null:
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

@optionalTypeArgs TResult map<TResult extends Object?>(TResult Function( _JobActions value)  $default,){
final _that = this;
switch (_that) {
case _JobActions():
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

@optionalTypeArgs TResult? mapOrNull<TResult extends Object?>(TResult? Function( _JobActions value)?  $default,){
final _that = this;
switch (_that) {
case _JobActions() when $default != null:
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

@optionalTypeArgs TResult maybeWhen<TResult extends Object?>(TResult Function( List<String> next, @JsonKey(name: 'can_cancel')  bool canCancel, @JsonKey(name: 'can_rate')  bool canRate)?  $default,{required TResult orElse(),}) {final _that = this;
switch (_that) {
case _JobActions() when $default != null:
return $default(_that.next,_that.canCancel,_that.canRate);case _:
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

@optionalTypeArgs TResult when<TResult extends Object?>(TResult Function( List<String> next, @JsonKey(name: 'can_cancel')  bool canCancel, @JsonKey(name: 'can_rate')  bool canRate)  $default,) {final _that = this;
switch (_that) {
case _JobActions():
return $default(_that.next,_that.canCancel,_that.canRate);case _:
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

@optionalTypeArgs TResult? whenOrNull<TResult extends Object?>(TResult? Function( List<String> next, @JsonKey(name: 'can_cancel')  bool canCancel, @JsonKey(name: 'can_rate')  bool canRate)?  $default,) {final _that = this;
switch (_that) {
case _JobActions() when $default != null:
return $default(_that.next,_that.canCancel,_that.canRate);case _:
  return null;

}
}

}

/// @nodoc
@JsonSerializable()

class _JobActions implements JobActions {
  const _JobActions({final  List<String> next = const <String>[], @JsonKey(name: 'can_cancel') this.canCancel = false, @JsonKey(name: 'can_rate') this.canRate = false}): _next = next;
  factory _JobActions.fromJson(Map<String, dynamic> json) => _$JobActionsFromJson(json);

 final  List<String> _next;
@override@JsonKey() List<String> get next {
  if (_next is EqualUnmodifiableListView) return _next;
  // ignore: implicit_dynamic_type
  return EqualUnmodifiableListView(_next);
}

@override@JsonKey(name: 'can_cancel') final  bool canCancel;
@override@JsonKey(name: 'can_rate') final  bool canRate;

/// Create a copy of JobActions
/// with the given fields replaced by the non-null parameter values.
@override @JsonKey(includeFromJson: false, includeToJson: false)
@pragma('vm:prefer-inline')
_$JobActionsCopyWith<_JobActions> get copyWith => __$JobActionsCopyWithImpl<_JobActions>(this, _$identity);

@override
Map<String, dynamic> toJson() {
  return _$JobActionsToJson(this, );
}

@override
bool operator ==(Object other) {
  return identical(this, other) || (other.runtimeType == runtimeType&&other is _JobActions&&const DeepCollectionEquality().equals(other._next, _next)&&(identical(other.canCancel, canCancel) || other.canCancel == canCancel)&&(identical(other.canRate, canRate) || other.canRate == canRate));
}

@JsonKey(includeFromJson: false, includeToJson: false)
@override
int get hashCode => Object.hash(runtimeType,const DeepCollectionEquality().hash(_next),canCancel,canRate);

@override
String toString() {
  return 'JobActions(next: $next, canCancel: $canCancel, canRate: $canRate)';
}


}

/// @nodoc
abstract mixin class _$JobActionsCopyWith<$Res> implements $JobActionsCopyWith<$Res> {
  factory _$JobActionsCopyWith(_JobActions value, $Res Function(_JobActions) _then) = __$JobActionsCopyWithImpl;
@override @useResult
$Res call({
 List<String> next,@JsonKey(name: 'can_cancel') bool canCancel,@JsonKey(name: 'can_rate') bool canRate
});




}
/// @nodoc
class __$JobActionsCopyWithImpl<$Res>
    implements _$JobActionsCopyWith<$Res> {
  __$JobActionsCopyWithImpl(this._self, this._then);

  final _JobActions _self;
  final $Res Function(_JobActions) _then;

/// Create a copy of JobActions
/// with the given fields replaced by the non-null parameter values.
@override @pragma('vm:prefer-inline') $Res call({Object? next = null,Object? canCancel = null,Object? canRate = null,}) {
  return _then(_JobActions(
next: null == next ? _self._next : next // ignore: cast_nullable_to_non_nullable
as List<String>,canCancel: null == canCancel ? _self.canCancel : canCancel // ignore: cast_nullable_to_non_nullable
as bool,canRate: null == canRate ? _self.canRate : canRate // ignore: cast_nullable_to_non_nullable
as bool,
  ));
}


}

// dart format on
