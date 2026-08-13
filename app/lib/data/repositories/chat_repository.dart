import 'package:dio/dio.dart';

import '../../core/network/dio_client.dart';
import '../api/chat_api.dart';
import '../models/conversation.dart';
import '../models/message.dart';
import '../models/paginated_result.dart';

class ChatRepository {
  ChatRepository({required ChatApi api, required Dio dio})
    : _api = api,
      _dio = dio;

  final ChatApi _api;
  // Sending a message is multipart (optional image attachment) — goes
  // straight through Dio, same reasoning as SellerRepository's uploads.
  final Dio _dio;

  Future<PaginatedResult<Conversation>> conversations({int page = 1}) async {
    try {
      final json = await _api.conversations(page);
      return PaginatedResult<Conversation>.fromJson(json as Map<String, dynamic>, Conversation.fromJson);
    } catch (e) {
      throw mapDioError(e);
    }
  }

  Future<Conversation> conversation(int id) async {
    try {
      final json = await _api.conversation(id);
      return Conversation.fromJson((json as Map<String, dynamic>)['data'] as Map<String, dynamic>);
    } catch (e) {
      throw mapDioError(e);
    }
  }

  Future<Conversation> startConversation({required int sellerId, int? productId}) async {
    try {
      final json = await _api.startConversation({'seller_id': sellerId, 'product_id': ?productId});
      return Conversation.fromJson((json as Map<String, dynamic>)['data'] as Map<String, dynamic>);
    } catch (e) {
      throw mapDioError(e);
    }
  }

  /// Refreshes the caller's typing signal — best-effort, so a transient
  /// failure here shouldn't surface as an error to the user typing.
  Future<void> sendTyping(int conversationId) async {
    try {
      await _api.sendTyping(conversationId);
    } catch (_) {}
  }

  Future<PaginatedResult<ChatMessage>> messages(int conversationId, {int page = 1}) async {
    try {
      final json = await _api.messages(conversationId, page);
      return PaginatedResult<ChatMessage>.fromJson(json as Map<String, dynamic>, ChatMessage.fromJson);
    } catch (e) {
      throw mapDioError(e);
    }
  }

  Future<ChatMessage> sendMessage({required int conversationId, String? body, String? attachmentPath}) async {
    try {
      final response = await _dio.post(
        '/conversations/$conversationId/messages',
        data: FormData.fromMap({
          'body': ?body,
          if (attachmentPath != null) 'attachment': await MultipartFile.fromFile(attachmentPath),
        }),
      );
      return ChatMessage.fromJson((response.data as Map<String, dynamic>)['data'] as Map<String, dynamic>);
    } catch (e) {
      throw mapDioError(e);
    }
  }
}
