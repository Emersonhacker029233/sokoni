import 'package:freezed_annotation/freezed_annotation.dart';

part 'app_notification.freezed.dart';
part 'app_notification.g.dart';

/// Mirrors `App\Http\Resources\AppNotificationResource`.
@freezed
abstract class AppNotification with _$AppNotification {
  const factory AppNotification({
    required int id,
    required String title,
    required String body,
    Map<String, dynamic>? payload,
    @JsonKey(name: 'is_read') @Default(false) bool isRead,
    @JsonKey(name: 'created_at') DateTime? createdAt,
  }) = _AppNotification;

  factory AppNotification.fromJson(Map<String, dynamic> json) => _$AppNotificationFromJson(json);
}
