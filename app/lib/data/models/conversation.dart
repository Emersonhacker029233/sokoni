import 'package:freezed_annotation/freezed_annotation.dart';

import 'product.dart';
import 'seller_summary.dart';
import 'user.dart';

part 'conversation.freezed.dart';
part 'conversation.g.dart';

/// Mirrors `App\Http\Resources\ConversationResource`.
@freezed
abstract class Conversation with _$Conversation {
  const factory Conversation({
    required int id,
    SokoniUser? buyer,
    SellerSummary? seller,
    Product? product,
    @JsonKey(name: 'order_id') int? orderId,
    @JsonKey(name: 'last_message') String? lastMessage,
    @JsonKey(name: 'last_message_at') DateTime? lastMessageAt,
    @JsonKey(name: 'unread_count') @Default(0) int unreadCount,
    @JsonKey(name: 'other_party_typing') @Default(false) bool otherPartyTyping,
  }) = _Conversation;

  factory Conversation.fromJson(Map<String, dynamic> json) => _$ConversationFromJson(json);
}
