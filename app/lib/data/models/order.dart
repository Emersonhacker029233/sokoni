import 'package:freezed_annotation/freezed_annotation.dart';

import 'json_readers.dart';
import 'order_item.dart';
import 'seller_summary.dart';
import 'user.dart';

part 'order.freezed.dart';
part 'order.g.dart';

/// One entry in `App\Models\Order::timeline()`.
@freezed
abstract class OrderTimelineStep with _$OrderTimelineStep {
  const factory OrderTimelineStep({
    @JsonKey(fromJson: readString) required String status,
    // Null only if the server ever sends a step without a time; the
    // timeline then shows the step without one instead of failing.
    @JsonKey(fromJson: readDateTimeOrNull) DateTime? at,
  }) = _OrderTimelineStep;

  factory OrderTimelineStep.fromJson(Map<String, dynamic> json) => _$OrderTimelineStepFromJson(json);
}

/// Mirrors `App\Http\Resources\OrderResource`.
@freezed
abstract class Order with _$Order {
  const factory Order({
    required int id,
    @JsonKey(fromJson: readString) required String code,
    // Read null-safely: the order-placing response used to send null here
    // (2026-11-04). Amounts below stay strict on purpose (json_readers.dart).
    @JsonKey(fromJson: readOrderStatus) required String status,
    required int subtotal,
    @JsonKey(name: 'delivery_fee') required int deliveryFee,
    required int total,
    @JsonKey(name: 'delivery_method', fromJson: readString) required String deliveryMethod,
    // Sokoni 1.1: 'dispatch' when a Bolt driver delivers (docs/delivery/
    // PLAN.md), null for every 1.0-style order.
    String? fulfilment,
    String? address,
    // Buyer only, dispatch orders: the number the Bolt driver calls.
    @JsonKey(name: 'delivery_phone') String? deliveryPhone,
    String? notes,
    @JsonKey(name: 'payment_method', fromJson: readString) required String paymentMethod,
    @JsonKey(name: 'payment_status') String? paymentStatus,
    @JsonKey(name: 'cancelled_reason') String? cancelledReason,
    SokoniUser? buyer,
    SellerSummary? seller,
    @Default(<OrderItem>[]) List<OrderItem> items,
    @JsonKey(name: 'conversation_id') int? conversationId,
    @Default(<OrderTimelineStep>[]) List<OrderTimelineStep> timeline,
    @JsonKey(name: 'has_review') bool? hasReview,
    @JsonKey(name: 'created_at') DateTime? createdAt,
  }) = _Order;

  factory Order.fromJson(Map<String, dynamic> json) => _$OrderFromJson(json);
}

extension OrderX on Order {
  bool get isCancellable => status == 'pending' || status == 'accepted' || status == 'ready';

  /// Delivered by a Bolt driver booked by Sokoni (Sokoni 1.1 delivery flow).
  bool get isDispatch => fulfilment == 'dispatch';
}
