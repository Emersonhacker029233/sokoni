import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/l10n/gen/app_localizations.dart';
import '../../../core/network/api_exception.dart';
import '../../../shared/widgets/empty_state.dart';
import 'moderation_menu.dart';

/// What a product, shop or chat shows when the server answers "blocked"
/// (an old link or notification opened after blocking). With
/// [BlockedException.blockedUserId] set, the viewer is the blocker and can
/// unblock from here; otherwise the other person blocked them, and the
/// page only says the account isn't available.
class BlockedState extends ConsumerWidget {
  const BlockedState({required this.error, this.name, super.key});

  final BlockedException error;
  final String? name;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = AppLocalizations.of(context);
    final blockedUserId = error.blockedUserId;

    if (blockedUserId == null) {
      return SokoniEmptyState(
        icon: Icons.block_rounded,
        title: l10n.blockedStateUnavailable,
        message: error.message,
      );
    }

    return SokoniEmptyState(
      icon: Icons.block_rounded,
      title: l10n.blockedStateTitle,
      message: l10n.blockedStateBody,
      actionLabel: l10n.unblockAction,
      onAction: () => unblockWithFeedback(context, ref, blockedUserId, name ?? l10n.blockedStateTitle),
    );
  }
}
