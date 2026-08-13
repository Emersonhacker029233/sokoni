// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'conversation.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

_Conversation _$ConversationFromJson(Map<String, dynamic> json) =>
    _Conversation(
      id: (json['id'] as num).toInt(),
      buyer: json['buyer'] == null
          ? null
          : SokoniUser.fromJson(json['buyer'] as Map<String, dynamic>),
      seller: json['seller'] == null
          ? null
          : SellerSummary.fromJson(json['seller'] as Map<String, dynamic>),
      product: json['product'] == null
          ? null
          : Product.fromJson(json['product'] as Map<String, dynamic>),
      orderId: (json['order_id'] as num?)?.toInt(),
      lastMessage: json['last_message'] as String?,
      lastMessageAt: json['last_message_at'] == null
          ? null
          : DateTime.parse(json['last_message_at'] as String),
      unreadCount: (json['unread_count'] as num?)?.toInt() ?? 0,
      otherPartyTyping: json['other_party_typing'] as bool? ?? false,
    );

Map<String, dynamic> _$ConversationToJson(_Conversation instance) =>
    <String, dynamic>{
      'id': instance.id,
      'buyer': instance.buyer?.toJson(),
      'seller': instance.seller?.toJson(),
      'product': instance.product?.toJson(),
      'order_id': instance.orderId,
      'last_message': instance.lastMessage,
      'last_message_at': instance.lastMessageAt?.toIso8601String(),
      'unread_count': instance.unreadCount,
      'other_party_typing': instance.otherPartyTyping,
    };
