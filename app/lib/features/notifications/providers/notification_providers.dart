import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/providers.dart';
import '../../../data/api/notification_api.dart';
import '../../../data/models/app_notification.dart';
import '../../../data/repositories/notification_repository.dart';

final notificationApiProvider = Provider<NotificationApi>((ref) {
  return NotificationApi(ref.watch(dioProvider));
});

final notificationRepositoryProvider = Provider<NotificationRepository>((ref) {
  return NotificationRepository(api: ref.watch(notificationApiProvider));
});

/// A single bounded page, same reasoning as the Updates tray/Offers row —
/// this drives a short list under a bell icon, not an infinite feed.
final notificationsProvider = FutureProvider.autoDispose<List<AppNotification>>((ref) async {
  ref.watch(authStateProvider);
  if (!ref.read(authStateProvider).isAuthenticated) return const [];
  final result = await ref.watch(notificationRepositoryProvider).notifications();
  return result.items;
});

final unreadNotificationCountProvider = Provider.autoDispose<int>((ref) {
  final notifications = ref.watch(notificationsProvider).value ?? const [];
  return notifications.where((n) => !n.isRead).length;
});
