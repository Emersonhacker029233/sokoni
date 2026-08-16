import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/l10n/gen/app_localizations.dart';
import '../../../core/network/api_exception.dart';
import '../../../core/theme/colors.dart';
import '../../../core/theme/dimens.dart';
import '../../../data/models/app_notification.dart';
import '../../../shared/widgets/empty_state.dart';
import '../../../shared/widgets/error_state.dart';
import '../providers/notification_providers.dart';

/// The Notifications tab reachable from Home's top bar (CLAUDE.md Part 3).
/// A real, persisted inbox — every event that already triggers a push
/// (order status, new message, new Update/Offer from a followed shop) also
/// lands here server-side, see `AppNotification`/`LogPushNotifier`.
class NotificationsScreen extends ConsumerWidget {
  const NotificationsScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = AppLocalizations.of(context);
    final notificationsAsync = ref.watch(notificationsProvider);

    return Scaffold(
      appBar: AppBar(
        title: Text(l10n.notificationsTitle),
        actions: [
          notificationsAsync.maybeWhen(
            data: (items) => items.any((n) => !n.isRead)
                ? TextButton(
                    onPressed: () async {
                      await ref.read(notificationRepositoryProvider).markAllRead();
                      ref.invalidate(notificationsProvider);
                    },
                    child: Text(l10n.notificationsMarkAllRead),
                  )
                : const SizedBox.shrink(),
            orElse: () => const SizedBox.shrink(),
          ),
        ],
      ),
      body: notificationsAsync.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (error, _) => SokoniErrorState(
          message: error is ApiException ? error.message : l10n.feedErrorBody,
          onRetry: () => ref.invalidate(notificationsProvider),
        ),
        data: (items) => items.isEmpty
            ? SokoniEmptyState(
                icon: Icons.notifications_none_rounded,
                title: l10n.notificationsEmptyTitle,
                message: l10n.notificationsEmptyBody,
              )
            : RefreshIndicator(
                onRefresh: () async => ref.invalidate(notificationsProvider),
                child: ListView.separated(
                  padding: const EdgeInsets.symmetric(vertical: SokoniDimens.space8),
                  itemCount: items.length,
                  separatorBuilder: (context, index) => const Divider(height: 1),
                  itemBuilder: (context, index) => _NotificationTile(notification: items[index]),
                ),
              ),
      ),
    );
  }
}

class _NotificationTile extends ConsumerWidget {
  const _NotificationTile({required this.notification});

  final AppNotification notification;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final textTheme = Theme.of(context).textTheme;

    return ListTile(
      onTap: () async {
        if (!notification.isRead) {
          await ref.read(notificationRepositoryProvider).markRead(notification.id);
          ref.invalidate(notificationsProvider);
        }
      },
      leading: Icon(
        Icons.notifications_rounded,
        color: notification.isRead ? Theme.of(context).hintColor : SokoniColors.sokoniYellow,
      ),
      title: Text(
        notification.title,
        style: textTheme.bodyMedium?.copyWith(fontWeight: notification.isRead ? FontWeight.normal : FontWeight.w700),
      ),
      subtitle: Text(notification.body, maxLines: 2, overflow: TextOverflow.ellipsis),
    );
  }
}
