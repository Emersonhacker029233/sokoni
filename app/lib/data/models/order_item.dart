import 'package:freezed_annotation/freezed_annotation.dart';

import 'json_readers.dart';

part 'order_item.freezed.dart';
part 'order_item.g.dart';

/// Mirrors `App\Http\Resources\OrderItemResource`.
@freezed
abstract class OrderItem with _$OrderItem {
  const factory OrderItem({
    required int id,
    @JsonKey(name: 'product_id') required int productId,
    @JsonKey(fromJson: readString) required String title,
    required int price,
    required int qty,
    @JsonKey(name: 'line_total') required int lineTotal,
  }) = _OrderItem;

  factory OrderItem.fromJson(Map<String, dynamic> json) => _$OrderItemFromJson(json);
}
