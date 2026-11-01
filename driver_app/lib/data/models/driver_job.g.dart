// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'driver_job.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

_DriverJob _$DriverJobFromJson(Map<String, dynamic> json) => _DriverJob(
  id: (json['id'] as num).toInt(),
  orderId: (json['order_id'] as num).toInt(),
  orderCode: json['order_code'] as String,
  status: json['status'] as String,
  isActive: json['is_active'] as bool? ?? false,
  distanceKm: (json['distance_km'] as num?)?.toDouble(),
  timeline:
      (json['timeline'] as List<dynamic>?)
          ?.map((e) => TimelineEntry.fromJson(e as Map<String, dynamic>))
          .toList() ??
      const <TimelineEntry>[],
  cancelledBy: json['cancelled_by'] as String?,
  cancelReason: json['cancel_reason'] as String?,
  failureReason: json['failure_reason'] as String?,
  createdAt: json['created_at'] == null
      ? null
      : DateTime.parse(json['created_at'] as String),
  fee: (json['fee'] as num?)?.toInt() ?? 0,
  commission: (json['commission'] as num?)?.toInt() ?? 0,
  driverEarnings: (json['driver_earnings'] as num?)?.toInt() ?? 0,
  earnedAmount: (json['earned_amount'] as num?)?.toInt(),
  paymentStatus: json['payment_status'] as String?,
  collectCash: (json['collect_cash'] as num?)?.toInt() ?? 0,
  shop: JobPlace.fromJson(json['shop'] as Map<String, dynamic>),
  customer: JobPlace.fromJson(json['customer'] as Map<String, dynamic>),
  items:
      (json['items'] as List<dynamic>?)
          ?.map((e) => JobItem.fromJson(e as Map<String, dynamic>))
          .toList() ??
      const <JobItem>[],
  notes: json['notes'] as String?,
  callAttempts: (json['call_attempts'] as num?)?.toInt() ?? 0,
  pinAttemptsLeft: (json['pin_attempts_left'] as num?)?.toInt() ?? 5,
  pinLocked: json['pin_locked'] as bool? ?? false,
  proofPhotoRequired: json['proof_photo_required'] as bool? ?? false,
  unreachableAvailableAt: json['unreachable_available_at'] == null
      ? null
      : DateTime.parse(json['unreachable_available_at'] as String),
  actions: json['actions'] == null
      ? const JobActions()
      : JobActions.fromJson(json['actions'] as Map<String, dynamic>),
);

Map<String, dynamic> _$DriverJobToJson(_DriverJob instance) =>
    <String, dynamic>{
      'id': instance.id,
      'order_id': instance.orderId,
      'order_code': instance.orderCode,
      'status': instance.status,
      'is_active': instance.isActive,
      'distance_km': instance.distanceKm,
      'timeline': instance.timeline.map((e) => e.toJson()).toList(),
      'cancelled_by': instance.cancelledBy,
      'cancel_reason': instance.cancelReason,
      'failure_reason': instance.failureReason,
      'created_at': instance.createdAt?.toIso8601String(),
      'fee': instance.fee,
      'commission': instance.commission,
      'driver_earnings': instance.driverEarnings,
      'earned_amount': instance.earnedAmount,
      'payment_status': instance.paymentStatus,
      'collect_cash': instance.collectCash,
      'shop': instance.shop.toJson(),
      'customer': instance.customer.toJson(),
      'items': instance.items.map((e) => e.toJson()).toList(),
      'notes': instance.notes,
      'call_attempts': instance.callAttempts,
      'pin_attempts_left': instance.pinAttemptsLeft,
      'pin_locked': instance.pinLocked,
      'proof_photo_required': instance.proofPhotoRequired,
      'unreachable_available_at': instance.unreachableAvailableAt
          ?.toIso8601String(),
      'actions': instance.actions.toJson(),
    };

_JobPlace _$JobPlaceFromJson(Map<String, dynamic> json) => _JobPlace(
  name: json['name'] as String?,
  address: json['address'] as String?,
  phone: json['phone'] as String?,
  lat: (json['lat'] as num).toDouble(),
  lng: (json['lng'] as num).toDouble(),
);

Map<String, dynamic> _$JobPlaceToJson(_JobPlace instance) => <String, dynamic>{
  'name': instance.name,
  'address': instance.address,
  'phone': instance.phone,
  'lat': instance.lat,
  'lng': instance.lng,
};

_JobItem _$JobItemFromJson(Map<String, dynamic> json) => _JobItem(
  title: json['title'] as String,
  qty: (json['qty'] as num?)?.toInt() ?? 1,
);

Map<String, dynamic> _$JobItemToJson(_JobItem instance) => <String, dynamic>{
  'title': instance.title,
  'qty': instance.qty,
};

_TimelineEntry _$TimelineEntryFromJson(Map<String, dynamic> json) =>
    _TimelineEntry(
      status: json['status'] as String?,
      at: json['at'] == null ? null : DateTime.parse(json['at'] as String),
      by: json['by'] as String?,
      reason: json['reason'] as String?,
    );

Map<String, dynamic> _$TimelineEntryToJson(_TimelineEntry instance) =>
    <String, dynamic>{
      'status': instance.status,
      'at': instance.at?.toIso8601String(),
      'by': instance.by,
      'reason': instance.reason,
    };

_JobActions _$JobActionsFromJson(Map<String, dynamic> json) => _JobActions(
  next:
      (json['next'] as List<dynamic>?)?.map((e) => e as String).toList() ??
      const <String>[],
  canCancel: json['can_cancel'] as bool? ?? false,
  canRate: json['can_rate'] as bool? ?? false,
);

Map<String, dynamic> _$JobActionsToJson(_JobActions instance) =>
    <String, dynamic>{
      'next': instance.next,
      'can_cancel': instance.canCancel,
      'can_rate': instance.canRate,
    };
