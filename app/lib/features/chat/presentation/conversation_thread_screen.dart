import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:image_picker/image_picker.dart';
import 'package:intl/intl.dart';

import '../../../core/l10n/gen/app_localizations.dart';
import '../../../core/network/api_exception.dart';
import '../../../core/router/routes.dart';
import '../../../core/theme/colors.dart';
import '../../../core/theme/dimens.dart';
import '../../../core/utils/formatters.dart';
import '../../../data/models/conversation.dart';
import '../../../data/models/message.dart';
import '../../../data/models/product.dart';
import '../../../shared/widgets/error_state.dart';
import '../../../shared/widgets/report_sheet.dart';
import '../providers/chat_providers.dart';
import '../../../shared/widgets/sokoni_network_image.dart';

/// The message thread — pinned product/order context, polling + read
/// receipts, typing indicator, image attachments (CLAUDE.md feature 5).
class ConversationThreadScreen extends ConsumerStatefulWidget {
  const ConversationThreadScreen({required this.conversationId, super.key});

  final int conversationId;

  @override
  ConsumerState<ConversationThreadScreen> createState() => _ConversationThreadScreenState();
}

class _ConversationThreadScreenState extends ConsumerState<ConversationThreadScreen> {
  final _bodyController = TextEditingController();
  final _scrollController = ScrollController();
  DateTime? _lastTypingSentAt;
  bool _sending = false;

  @override
  void dispose() {
    _bodyController.dispose();
    _scrollController.dispose();
    super.dispose();
  }

  void _onBodyChanged(String value) {
    final now = DateTime.now();
    if (value.isNotEmpty &&
        (_lastTypingSentAt == null || now.difference(_lastTypingSentAt!) > const Duration(seconds: 3))) {
      _lastTypingSentAt = now;
      ref.read(chatThreadProvider(widget.conversationId).notifier).notifyTyping();
    }
  }

  Future<void> _send() async {
    final body = _bodyController.text.trim();
    if (body.isEmpty) return;
    _bodyController.clear();
    setState(() => _sending = true);
    try {
      await ref.read(chatThreadProvider(widget.conversationId).notifier).sendMessage(body: body);
    } finally {
      if (mounted) setState(() => _sending = false);
    }
  }

  Future<void> _attachImage() async {
    final picked = await ImagePicker().pickImage(source: ImageSource.gallery, imageQuality: 85);
    if (picked == null) return;
    setState(() => _sending = true);
    try {
      await ref.read(chatThreadProvider(widget.conversationId).notifier).sendMessage(attachmentPath: picked.path);
    } finally {
      if (mounted) setState(() => _sending = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    final threadAsync = ref.watch(chatThreadProvider(widget.conversationId));
    final conversationsAsync = ref.watch(conversationsProvider);
    final conversation = conversationsAsync.maybeWhen(
      data: (list) => list.where((c) => c.id == widget.conversationId).firstOrNull,
      orElse: () => null,
    );

    return Scaffold(
      appBar: AppBar(
        title: Text(conversation?.seller?.shopName ?? conversation?.buyer?.name ?? l10n.chatTitle),
      ),
      body: Column(
        children: [
          if (conversation?.product != null) _PinnedProductContext(conversation: conversation!),
          Expanded(
            child: threadAsync.when(
              loading: () => const Center(child: CircularProgressIndicator()),
              error: (error, _) => SokoniErrorState(
                message: error is ApiException ? error.message : '$error',
                onRetry: () => ref.invalidate(chatThreadProvider(widget.conversationId)),
              ),
              data: (state) {
                WidgetsBinding.instance.addPostFrameCallback((_) {
                  if (_scrollController.hasClients) {
                    _scrollController.jumpTo(_scrollController.position.maxScrollExtent);
                  }
                });
                return ListView.builder(
                  controller: _scrollController,
                  padding: const EdgeInsets.all(SokoniDimens.space16),
                  itemCount: state.messages.length + (state.otherPartyTyping ? 1 : 0),
                  itemBuilder: (context, index) {
                    if (index == state.messages.length) {
                      return Align(
                        alignment: Alignment.centerLeft,
                        child: Padding(
                          padding: const EdgeInsets.symmetric(vertical: SokoniDimens.space4),
                          child: Text(l10n.chatTyping, style: Theme.of(context).textTheme.bodySmall),
                        ),
                      );
                    }
                    return _MessageBubble(message: state.messages[index]);
                  },
                );
              },
            ),
          ),
          SafeArea(
            top: false,
            child: Padding(
              padding: const EdgeInsets.symmetric(horizontal: SokoniDimens.space12, vertical: SokoniDimens.space8),
              child: Row(
                children: [
                  IconButton(
                    icon: const Icon(Icons.image_outlined),
                    onPressed: _sending ? null : _attachImage,
                  ),
                  Expanded(
                    child: TextField(
                      controller: _bodyController,
                      onChanged: _onBodyChanged,
                      decoration: InputDecoration(
                        hintText: l10n.chatMessageHint,
                        border: OutlineInputBorder(borderRadius: BorderRadius.circular(SokoniDimens.radiusField)),
                      ),
                      minLines: 1,
                      maxLines: 4,
                    ),
                  ),
                  IconButton(
                    icon: _sending
                        ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2))
                        : const Icon(Icons.send_rounded),
                    onPressed: _sending ? null : _send,
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _PinnedProductContext extends StatelessWidget {
  const _PinnedProductContext({required this.conversation});

  final Conversation conversation;

  @override
  Widget build(BuildContext context) {
    final product = conversation.product!;
    return Material(
      color: Theme.of(context).colorScheme.surfaceContainerHighest,
      child: InkWell(
        onTap: () => context.push(SokoniRoutes.product(product.id)),
        child: Padding(
          padding: const EdgeInsets.all(SokoniDimens.space12),
          child: Row(
            children: [
              if (product.coverImageUrl != null)
                ClipRRect(
                  borderRadius: BorderRadius.circular(SokoniDimens.radiusChip),
                  child: SokoniNetworkImage(
                    imageUrl: product.coverImageUrl!,
                    width: 40,
                    height: 40,
                    fit: BoxFit.cover,
                  ),
                ),
              const SizedBox(width: SokoniDimens.space8),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(product.title, maxLines: 1, overflow: TextOverflow.ellipsis),
                    Text(SokoniFormat.tzs(product.price), style: Theme.of(context).textTheme.bodySmall),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _MessageBubble extends ConsumerWidget {
  const _MessageBubble({required this.message});

  final ChatMessage message;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final formatter = DateFormat('HH:mm');
    final align = message.isMine ? CrossAxisAlignment.end : CrossAxisAlignment.start;
    final bubbleColor = message.isMine
        ? SokoniColors.sokoniYellow
        : Theme.of(context).colorScheme.surfaceContainerHighest;

    return Padding(
      padding: const EdgeInsets.symmetric(vertical: SokoniDimens.space4),
      child: Column(
        crossAxisAlignment: align,
        children: [
          GestureDetector(
            onLongPress: message.isMine
                ? null
                : () => showReportSheet(context, ref, reportableType: 'message', reportableId: message.id),
            child: Container(
              constraints: BoxConstraints(maxWidth: MediaQuery.sizeOf(context).width * 0.72),
              padding: const EdgeInsets.all(SokoniDimens.space12),
              decoration: BoxDecoration(
                color: bubbleColor,
                borderRadius: BorderRadius.circular(SokoniDimens.radiusCard),
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  if (message.attachment != null)
                    ClipRRect(
                      borderRadius: BorderRadius.circular(SokoniDimens.radiusChip),
                      child: SokoniNetworkImage(
                        imageUrl: message.attachment!,
                        width: 180,
                        fit: BoxFit.cover,
                      ),
                    ),
                  if (message.body != null && message.body!.isNotEmpty)
                    Text(
                      message.body!,
                      // "Mine" bubbles stay sokoniYellow regardless of
                      // theme, but with no explicit colour here the text
                      // followed the ambient theme text colour, which
                      // flips to near-white in dark mode — near-white on
                      // bright yellow is poor contrast. onYellow (black)
                      // is correct for this bubble in both themes; the
                      // received-message bubble already tracks the theme
                      // correctly via surfaceContainerHighest, so it's
                      // left to inherit as before.
                      style: message.isMine ? const TextStyle(color: SokoniColors.onYellow) : null,
                    ),
                ],
              ),
            ),
          ),
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: SokoniDimens.space4),
            child: Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                if (message.createdAt != null)
                  Text(formatter.format(message.createdAt!), style: Theme.of(context).textTheme.bodySmall),
                if (message.isMine) ...[
                  const SizedBox(width: 4),
                  Icon(
                    message.readAt != null ? Icons.done_all_rounded : Icons.done_rounded,
                    size: 14,
                    color: message.readAt != null ? SokoniColors.success : null,
                  ),
                ],
              ],
            ),
          ),
        ],
      ),
    );
  }
}
