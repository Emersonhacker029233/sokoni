import 'package:freezed_annotation/freezed_annotation.dart';

import 'order_item.dart';
import 'seller_summary.dart';
import 'user.dart';

part 'order.freezed.dart';
part 'order.g.dart';

/// One entry in `App\Models\Order::timeline()`.
@freezed
abstract class OrderTimelineStep with _$OrderTimelineStep {
  const factory OrderTimelineStep({
    required String status,
    required DateTime at,
  }) = _OrderTimelineStep;

  factory OrderTimelineStep.fromJson(Map<String, dynamic> json) => _$OrderTimelineStepFromJson(json);
}

/// Mirrors `App\Http\Resources\OrderResource`.
@freezed
abstract class Order with _$Order {
  const factory Order({
    required int id,
    required String code,
    required String status,
    required int subtotal,
    @JsonKey(name: 'delivery_fee') required int deliveryFee,
    required int total,
    @JsonKey(name: 'delivery_method') required String deliveryMethod,
    String? address,
    String? notes,
    @JsonKey(name: 'payment_method') required String paymentMethod,
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
}
