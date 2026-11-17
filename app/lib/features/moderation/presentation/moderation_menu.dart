import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/l10n/gen/app_localizations.dart';
import '../../../core/motion/sokoni_bottom_sheet.dart';
import '../../../core/network/api_exception.dart';
import '../../../core/providers.dart';
import '../../../core/theme/dimens.dart';
import '../../../shared/widgets/report_sheet.dart';
import '../../auth/presentation/sign_in_prompt_sheet.dart';
import '../../auth/providers/auth_providers.dart';
import '../providers/block_providers.dart';

/// What the "⋯" menu offers for one piece of someone else's content
/// (Apple Guideline 1.2): report it, and block the person behind it.
class ModerationTarget {
  const ModerationTarget({
    required this.reportType,
    required this.reportId,
    required this.reportLabel,
    required this.authorName,
    this.authorUserId,
    this.authorSellerId,
    this.authorHandle,
    this.hint,
  }) : assert(authorUserId != null || authorSellerId != null, 'Someone must be blockable');

  /// The API's report type: product, shop, message, comment, review,
  /// showcase, update, offer or user.
  final String reportType;
  final int reportId;

  /// "Report this listing", "Report this comment", ...
  final String reportLabel;

  /// Shown in "Block {name}": the shop's name for shop content.
  final String authorName;

  /// Block by person (comments, reviews, chats) ...
  final int? authorUserId;

  /// ... or by shop (listings, offers, showcases, updates).
  final int? authorSellerId;

  /// The shop's handle, to recognise the viewer's own shop.
  final String? authorHandle;

  /// Optional line under the report option.
  final String? hint;
}

/// The "⋯" button every screen showing someone else's content carries.
/// Hidden on the viewer's own content.
class ModerationMenuButton extends ConsumerWidget {
  const ModerationMenuButton({required this.target, this.color, super.key});

  final ModerationTarget target;
  final Color? color;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    if (_isOwn(ref, target)) return const SizedBox.shrink();

    return IconButton(
      icon: const Icon(Icons.more_horiz_rounded),
      color: color,
      tooltip: AppLocalizations.of(context).moderationMenuTooltip,
      onPressed: () => showModerationMenu(context, ref, target),
    );
  }
}

bool _isOwn(WidgetRef ref, ModerationTarget target) {
  if (!ref.watch(authStateProvider).isAuthenticated) return false;
  final me = ref.watch(currentUserProvider).value;
  if (me == null) return false;

  return (target.authorUserId != null && target.authorUserId == me.id) ||
      (target.authorHandle != null && target.authorHandle == me.sellerHandle);
}

Future<void> showModerationMenu(BuildContext context, WidgetRef ref, ModerationTarget target) {
  final l10n = AppLocalizations.of(context);
  if (!ref.read(authStateProvider).isAuthenticated) {
    return showSignInPrompt(context, message: l10n.guestPromptReport);
  }

  // The menu's own `sheetRef` dies with the sheet, which closes before
  // reporting or blocking starts, so both run on the caller's [ref].
  return showSokoniBottomSheet<void>(
    context: context,
    initialChildSize: 0.32,
    builder: (sheetContext) => Consumer(
      builder: (sheetContext, sheetRef, _) {
        final blocked = sheetRef.watch(blockedFilterProvider);
        final isBlocked = blocked.hidesUser(target.authorUserId) || blocked.hidesShop(target.authorSellerId);

        return Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            ListTile(
              leading: const Icon(Icons.flag_outlined),
              title: Text(target.reportLabel),
              subtitle: target.hint == null ? null : Text(target.hint!),
              onTap: () {
                Navigator.of(sheetContext).pop();
                showReportSheet(context, ref, reportableType: target.reportType, reportableId: target.reportId);
              },
            ),
            ListTile(
              leading: Icon(isBlocked ? Icons.lock_open_rounded : Icons.block_rounded, color: Theme.of(sheetContext).colorScheme.error),
              title: Text(
                isBlocked ? l10n.moderationUnblock(target.authorName) : l10n.moderationBlock(target.authorName),
                style: TextStyle(color: Theme.of(sheetContext).colorScheme.error),
              ),
              onTap: () {
                Navigator.of(sheetContext).pop();
                if (isBlocked) {
                  final account = blocked.forUser(target.authorUserId) ?? blocked.forShop(target.authorSellerId);
                  if (account != null) unblockWithFeedback(context, ref, account.userId, target.authorName);
                } else {
                  confirmAndBlock(context, ref, target);
                }
              },
            ),
            const SizedBox(height: SokoniDimens.space8),
          ],
        );
      },
    ),
  );
}

/// Asks once, then blocks. Their content disappears from every list at
/// once (BlockedAccountsController) and the moderators are notified
/// (the server files a report for every block).
Future<void> confirmAndBlock(BuildContext context, WidgetRef ref, ModerationTarget target) async {
  final l10n = AppLocalizations.of(context);
  final messenger = ScaffoldMessenger.of(context);

  final confirmed = await showDialog<bool>(
    context: context,
    builder: (dialogContext) => AlertDialog(
      title: Text(l10n.blockConfirmTitle(target.authorName)),
      content: Text(l10n.blockConfirmBody),
      actions: [
        TextButton(onPressed: () => Navigator.of(dialogContext).pop(false), child: Text(l10n.commonCancel)),
        FilledButton(
          style: FilledButton.styleFrom(backgroundColor: Theme.of(dialogContext).colorScheme.error),
          onPressed: () => Navigator.of(dialogContext).pop(true),
          child: Text(l10n.blockConfirmAction),
        ),
      ],
    ),
  );
  if (confirmed != true) return;

  try {
    await ref.read(blockedAccountsProvider.notifier).block(
      userId: target.authorSellerId == null ? target.authorUserId : null,
      sellerId: target.authorSellerId,
    );
    messenger.showSnackBar(SnackBar(content: Text(l10n.blockDone(target.authorName))));
  } on ApiException catch (e) {
    messenger.showSnackBar(SnackBar(content: Text(e.message)));
  }
}

Future<void> unblockWithFeedback(BuildContext context, WidgetRef ref, int userId, String name) async {
  final l10n = AppLocalizations.of(context);
  final messenger = ScaffoldMessenger.of(context);
  try {
    await ref.read(blockedAccountsProvider.notifier).unblock(userId);
    messenger.showSnackBar(SnackBar(content: Text(l10n.unblockDone(name))));
  } on ApiException catch (e) {
    messenger.showSnackBar(SnackBar(content: Text(e.message)));
  }
}
