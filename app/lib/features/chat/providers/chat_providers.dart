import 'dart:async';

import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/providers.dart';
import '../../../data/api/chat_api.dart';
import '../../../data/models/conversation.dart';
import '../../../data/models/message.dart';
import '../../../data/repositories/chat_repository.dart';

final chatApiProvider = Provider<ChatApi>((ref) => ChatApi(ref.watch(dioProvider)));

final chatRepositoryProvider = Provider<ChatRepository>((ref) {
  return ChatRepository(api: ref.watch(chatApiProvider), dio: ref.watch(dioProvider));
});

/// Conversation list — CLAUDE.md feature 5. Polled at the same 5s cadence
/// as an open thread so unread counts/last-message previews stay fresh
/// without a WebSocket server.
final conversationsProvider = FutureProvider.autoDispose<List<Conversation>>((ref) async {
  final timer = Timer.periodic(const Duration(seconds: 5), (_) => ref.invalidateSelf());
  ref.onDispose(timer.cancel);
  final result = await ref.watch(chatRepositoryProvider).conversations();
  return result.items;
});

class ThreadState {
  const ThreadState({required this.messages, required this.otherPartyTyping});

  final List<ChatMessage> messages;
  final bool otherPartyTyping;
}

/// One conversation thread: messages + the other party's typing state,
/// both re-polled every 5s while the thread is open (CLAUDE.md feature 5:
/// "5s polling while a thread is open plus FCM data messages for
/// background delivery — no WebSocket server"). Fetching messages also
/// marks the other party's messages read server-side (see
/// `MessageController::index`), so polling doubles as the read-receipt
/// mechanism with no separate call needed.
class ChatThreadController extends AsyncNotifier<ThreadState> {
  ChatThreadController(this.conversationId);

  final int conversationId;
  Timer? _pollTimer;

  @override
  Future<ThreadState> build() async {
    _pollTimer = Timer.periodic(const Duration(seconds: 5), (_) => _poll());
    ref.onDispose(() => _pollTimer?.cancel());
    return _fetch();
  }

  Future<ThreadState> _fetch() async {
    final repo = ref.read(chatRepositoryProvider);
    final messagesResult = await repo.messages(conversationId);
    final conversation = await repo.conversation(conversationId);
    return ThreadState(messages: messagesResult.items, otherPartyTyping: conversation.otherPartyTyping);
  }

  Future<void> _poll() async {
    try {
      final next = await _fetch();
      state = AsyncData(next);
    } catch (_) {
      // Transient poll failures keep showing the last good state rather
      // than flashing an error every 5s on a flaky connection.
    }
  }

  Future<void> sendMessage({String? body, String? attachmentPath}) async {
    await ref.read(chatRepositoryProvider).sendMessage(
      conversationId: conversationId,
      body: body,
      attachmentPath: attachmentPath,
    );
    await _poll();
  }

  Future<void> notifyTyping() {
    return ref.read(chatRepositoryProvider).sendTyping(conversationId);
  }
}

final chatThreadProvider = AsyncNotifierProvider.autoDispose.family<ChatThreadController, ThreadState, int>(
  ChatThreadController.new,
);
