import 'package:dio/dio.dart' hide Headers;
import 'package:retrofit/retrofit.dart';

part 'order_api.g.dart';

/// Return types are `dynamic` throughout, not typed models — see
/// `CatalogApi`'s docblock for why (retrofit_generator codegen bug).
@RestApi()
abstract class OrderApi {
  factory OrderApi(Dio dio, {String baseUrl}) = _OrderApi;

  @GET('/orders')
  Future<dynamic> myOrders(@Query('page') int page);

  @GET('/shop/orders')
  Future<dynamic> shopOrders(@Query('page') int page);

  @GET('/orders/{id}')
  Future<dynamic> order(@Path('id') int id);

  @POST('/orders')
  Future<dynamic> checkout(@Body() Map<String, dynamic> body);

  @PATCH('/orders/{id}/status')
  Future<dynamic> updateStatus(@Path('id') int id, @Body() Map<String, dynamic> body);
}
