import '../../core/network/dio_client.dart';
import '../api/notification_api.dart';
import '../models/app_notification.dart';
import '../models/paginated_result.dart';

class NotificationRepository {
  NotificationRepository({required NotificationApi api}) : _api = api;

  final NotificationApi _api;

  Future<PaginatedResult<AppNotification>> notifications({int page = 1}) async {
    try {
      final json = await _api.notifications(page);
      return PaginatedResult<AppNotification>.fromJson(json as Map<String, dynamic>, AppNotification.fromJson);
    } catch (e) {
      throw mapDioError(e);
    }
  }

  Future<AppNotification> markRead(int id) async {
    try {
      final json = await _api.markRead(id);
      return AppNotification.fromJson((json as Map<String, dynamic>)['data'] as Map<String, dynamic>);
    } catch (e) {
      throw mapDioError(e);
    }
  }

  Future<void> markAllRead() async {
    try {
      await _api.markAllRead();
    } catch (e) {
      throw mapDioError(e);
    }
  }
}
