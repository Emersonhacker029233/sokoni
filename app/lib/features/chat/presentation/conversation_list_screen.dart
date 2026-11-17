import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';

import '../../../core/l10n/gen/app_localizations.dart';
import '../../../core/network/api_exception.dart';
import '../../../core/router/routes.dart';
import '../../../core/theme/colors.dart';
import '../../../core/theme/dimens.dart';
import '../../../data/models/conversation.dart';
import '../../../shared/widgets/empty_state.dart';
import '../../../shared/widgets/error_state.dart';
import '../../moderation/providers/block_providers.dart';
import '../providers/chat_providers.dart';

class ConversationListScreen extends ConsumerWidget {
  const ConversationListScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = AppLocalizations.of(context);
    final conversationsAsync = ref.watch(conversationsProvider);

    return Scaffold(
      appBar: AppBar(title: Text(l10n.chatTitle)),
      body: conversationsAsync.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (error, _) => SokoniErrorState(
          message: error is ApiException ? error.message : '$error',
          onRetry: () => ref.invalidate(conversationsProvider),
        ),
        data: (allConversations) {
          // Apple Guideline 1.2: a chat with someone just blocked leaves
          // the list at once; the other person is either the shop or the
          // buyer, never the viewer themselves.
          final blocked = ref.watch(blockedFilterProvider);
          final conversations = allConversations
              .where((c) => !blocked.hidesShop(c.seller?.id) && !blocked.hidesUser(c.buyer?.id))
              .toList();
          if (conversations.isEmpty) {
            return SokoniEmptyState(
              icon: Icons.chat_bubble_outline_rounded,
              title: l10n.chatEmptyTitle,
              message: l10n.chatEmptyBody,
            );
          }
          return RefreshIndicator(
            onRefresh: () async => ref.invalidate(conversationsProvider),
            child: ListView.separated(
              padding: const EdgeInsets.symmetric(vertical: SokoniDimens.space8),
              itemCount: conversations.length,
              separatorBuilder: (_, _) => const Divider(height: 1),
              itemBuilder: (context, index) => _ConversationTile(conversation: conversations[index]),
            ),
          );
        },
      ),
    );
  }
}

class _ConversationTile extends StatelessWidget {
  const _ConversationTile({required this.conversation});

  final Conversation conversation;

  @override
  Widget build(BuildContext context) {
    final title = conversation.seller?.shopName ?? conversation.buyer?.name ?? '';
    final formatter = DateFormat('d MMM');

    return ListTile(
      leading: CircleAvatar(child: Text(title.isEmpty ? '?' : title[0].toUpperCase())),
      title: Text(title, maxLines: 1, overflow: TextOverflow.ellipsis),
      subtitle: Text(
        conversation.lastMessage ?? '',
        maxLines: 1,
        overflow: TextOverflow.ellipsis,
      ),
      trailing: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        crossAxisAlignment: CrossAxisAlignment.end,
        children: [
          if (conversation.lastMessageAt != null)
            Text(formatter.format(conversation.lastMessageAt!), style: Theme.of(context).textTheme.bodySmall),
          if (conversation.unreadCount > 0) ...[
            const SizedBox(height: 4),
            Container(
              width: 20,
              height: 20,
              alignment: Alignment.center,
              decoration: const BoxDecoration(color: SokoniColors.sokoniYellow, shape: BoxShape.circle),
              child: Text(
                '${conversation.unreadCount}',
                style: const TextStyle(fontSize: 11, fontWeight: FontWeight.w700, color: SokoniColors.onYellow),
              ),
            ),
          ],
        ],
      ),
      onTap: () => context.push(SokoniRoutes.conversation(conversation.id)),
    );
  }
}
