import 'package:flutter/material.dart';

import '../../../core/l10n/gen/app_localizations.dart';
import '../../../shared/widgets/empty_state.dart';

/// The message thread itself (polling + FCM, read receipts, attachments)
/// is Phase 8. This confirms the conversation was really created against
/// the API — not a dead link — while the full chat UI is still ahead.
class ConversationPlaceholderScreen extends StatelessWidget {
  const ConversationPlaceholderScreen({required this.conversationId, super.key});

  final int conversationId;

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    return Scaffold(
      appBar: AppBar(title: Text('#$conversationId')),
      body: SokoniEmptyState(
        icon: Icons.chat_bubble_outline_rounded,
        title: l10n.productMessageSeller,
        message: l10n.chatComingSoon,
      ),
    );
  }
}
