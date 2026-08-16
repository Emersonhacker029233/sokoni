import 'package:dio/dio.dart' hide Headers;
import 'package:retrofit/retrofit.dart';

part 'notification_api.g.dart';

@RestApi()
abstract class NotificationApi {
  factory NotificationApi(Dio dio, {String baseUrl}) = _NotificationApi;

  @GET('/notifications')
  Future<dynamic> notifications(@Query('page') int page);

  @PATCH('/notifications/{id}/read')
  Future<dynamic> markRead(@Path('id') int id);

  @PATCH('/notifications/read-all')
  Future<void> markAllRead();
}
