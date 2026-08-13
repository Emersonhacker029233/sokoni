import 'package:dio/dio.dart' hide Headers;
import 'package:retrofit/retrofit.dart';

part 'review_api.g.dart';

@RestApi()
abstract class ReviewApi {
  factory ReviewApi(Dio dio, {String baseUrl}) = _ReviewApi;

  @POST('/orders/{orderId}/review')
  Future<dynamic> submit(@Path('orderId') int orderId, @Body() Map<String, dynamic> body);

  @PATCH('/reviews/{id}/reply')
  Future<dynamic> reply(@Path('id') int id, @Body() Map<String, dynamic> body);
}
