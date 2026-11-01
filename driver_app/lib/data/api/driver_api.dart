import 'package:dio/dio.dart' hide Headers;
import 'package:retrofit/retrofit.dart';

part 'driver_api.g.dart';

/// `/api/driver/*` (docs/delivery/API.md). Every route answers 404 while
/// the server has `DELIVERY_ENABLED=false`. Multipart uploads go through
/// DriverRepository with raw Dio for upload progress.
@RestApi()
abstract class DriverApi {
  factory DriverApi(Dio dio, {String baseUrl}) = _DriverApi;

  @GET('/driver/me')
  Future<dynamic> me();

  @POST('/driver/profile')
  Future<dynamic> saveProfile(@Body() Map<String, dynamic> body);

  @PUT('/driver/vehicle')
  Future<dynamic> saveVehicle(@Body() Map<String, dynamic> body);

  @POST('/driver/profile/submit')
  Future<dynamic> submit();

  @POST('/driver/devices')
  Future<dynamic> registerDevice(@Body() Map<String, dynamic> body);

  @PATCH('/driver/availability')
  Future<dynamic> setAvailability(@Body() Map<String, dynamic> body);

  @POST('/driver/location')
  Future<dynamic> sendLocation(@Body() Map<String, dynamic> body);

  @GET('/driver/offers')
  Future<dynamic> offers();

  @POST('/driver/offers/{job}/accept')
  Future<dynamic> accept(@Path('job') int jobId);

  @POST('/driver/offers/{job}/decline')
  Future<dynamic> decline(@Path('job') int jobId);

  @GET('/driver/jobs/active')
  Future<dynamic> activeJob();

  @GET('/driver/jobs')
  Future<dynamic> jobs(@Query('page') int page);

  @GET('/driver/jobs/{job}')
  Future<dynamic> job(@Path('job') int jobId);

  @POST('/driver/jobs/{job}/status')
  Future<dynamic> updateStatus(@Path('job') int jobId, @Body() Map<String, dynamic> body);

  @POST('/driver/jobs/{job}/call-attempts')
  Future<dynamic> callAttempt(@Path('job') int jobId);

  @POST('/driver/jobs/{job}/rating')
  Future<dynamic> rateCustomer(@Path('job') int jobId, @Body() Map<String, dynamic> body);

  @GET('/driver/earnings')
  Future<dynamic> earnings(@Query('period') String period, @Query('date') String? date);

  @POST('/delivery/realtime-token')
  Future<dynamic> realtimeToken();
}
