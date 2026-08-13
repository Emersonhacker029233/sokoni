import 'package:dio/dio.dart' hide Headers;
import 'package:retrofit/retrofit.dart';

part 'seller_api.g.dart';

/// Return types are `dynamic` throughout, parsed by hand in
/// SellerRepository — see CatalogApi's docblock for why.
@RestApi()
abstract class SellerApi {
  factory SellerApi(Dio dio, {String baseUrl}) = _SellerApi;

  @GET('/sellers')
  Future<dynamic> sellers(@Queries() Map<String, dynamic> query);

  @GET('/sellers/{handle}')
  Future<dynamic> show(@Path('handle') String handle);

  /// Onboarding wizard step 1.
  @POST('/sellers')
  Future<dynamic> submitBusiness(@Body() Map<String, dynamic> body);

  /// Onboarding wizard step 2. Takes the seller's numeric id, not handle —
  /// the handle isn't confirmed reachable until after step 1 completes.
  @PATCH('/sellers/{id}/location')
  Future<dynamic> submitLocation(@Path('id') int id, @Body() Map<String, dynamic> body);

  @GET('/sellers/{handle}/reviews')
  Future<dynamic> reviews(@Path('handle') String handle, @Query('page') int page);

  @POST('/conversations')
  Future<dynamic> startConversation(@Body() Map<String, dynamic> body);
}
