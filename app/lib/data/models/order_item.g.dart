// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'order_item.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

_OrderItem _$OrderItemFromJson(Map<String, dynamic> json) => _OrderItem(
  id: (json['id'] as num).toInt(),
  productId: (json['product_id'] as num).toInt(),
  title: json['title'] as String,
  price: (json['price'] as num).toInt(),
  qty: (json['qty'] as num).toInt(),
  lineTotal: (json['line_total'] as num).toInt(),
);

Map<String, dynamic> _$OrderItemToJson(_OrderItem instance) =>
    <String, dynamic>{
      'id': instance.id,
      'product_id': instance.productId,
      'title': instance.title,
      'price': instance.price,
      'qty': instance.qty,
      'line_total': instance.lineTotal,
    };
