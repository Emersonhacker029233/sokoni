// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'order.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

_OrderTimelineStep _$OrderTimelineStepFromJson(Map<String, dynamic> json) =>
    _OrderTimelineStep(
      status: json['status'] as String,
      at: DateTime.parse(json['at'] as String),
    );

Map<String, dynamic> _$OrderTimelineStepToJson(_OrderTimelineStep instance) =>
    <String, dynamic>{
      'status': instance.status,
      'at': instance.at.toIso8601String(),
    };

_Order _$OrderFromJson(Map<String, dynamic> json) => _Order(
  id: (json['id'] as num).toInt(),
  code: json['code'] as String,
  status: json['status'] as String,
  subtotal: (json['subtotal'] as num).toInt(),
  deliveryFee: (json['delivery_fee'] as num).toInt(),
  total: (json['total'] as num).toInt(),
  deliveryMethod: json['delivery_method'] as String,
  address: json['address'] as String?,
  notes: json['notes'] as String?,
  paymentMethod: json['payment_method'] as String,
  paymentStatus: json['payment_status'] as String?,
  cancelledReason: json['cancelled_reason'] as String?,
  buyer: json['buyer'] == null
      ? null
      : SokoniUser.fromJson(json['buyer'] as Map<String, dynamic>),
  seller: json['seller'] == null
      ? null
      : SellerSummary.fromJson(json['seller'] as Map<String, dynamic>),
  items:
      (json['items'] as List<dynamic>?)
          ?.map((e) => OrderItem.fromJson(e as Map<String, dynamic>))
          .toList() ??
      const <OrderItem>[],
  conversationId: (json['conversation_id'] as num?)?.toInt(),
  timeline:
      (json['timeline'] as List<dynamic>?)
          ?.map((e) => OrderTimelineStep.fromJson(e as Map<String, dynamic>))
          .toList() ??
      const <OrderTimelineStep>[],
  hasReview: json['has_review'] as bool?,
  createdAt: json['created_at'] == null
      ? null
      : DateTime.parse(json['created_at'] as String),
);

Map<String, dynamic> _$OrderToJson(_Order instance) => <String, dynamic>{
  'id': instance.id,
  'code': instance.code,
  'status': instance.status,
  'subtotal': instance.subtotal,
  'delivery_fee': instance.deliveryFee,
  'total': instance.total,
  'delivery_method': instance.deliveryMethod,
  'address': instance.address,
  'notes': instance.notes,
  'payment_method': instance.paymentMethod,
  'payment_status': instance.paymentStatus,
  'cancelled_reason': instance.cancelledReason,
  'buyer': instance.buyer?.toJson(),
  'seller': instance.seller?.toJson(),
  'items': instance.items.map((e) => e.toJson()).toList(),
  'conversation_id': instance.conversationId,
  'timeline': instance.timeline.map((e) => e.toJson()).toList(),
  'has_review': instance.hasReview,
  'created_at': instance.createdAt?.toIso8601String(),
};
