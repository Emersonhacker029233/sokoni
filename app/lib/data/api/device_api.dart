import 'package:dio/dio.dart' hide Headers;
import 'package:retrofit/retrofit.dart';

part 'device_api.g.dart';

@RestApi()
abstract class DeviceApi {
  factory DeviceApi(Dio dio, {String baseUrl}) = _DeviceApi;

  @POST('/devices')
  Future<void> register(@Body() Map<String, dynamic> body);
}
