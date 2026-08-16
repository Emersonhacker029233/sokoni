import 'package:dio/dio.dart' hide Headers;
import 'package:retrofit/retrofit.dart';

part 'offers_api.g.dart';

/// Return types are `dynamic` throughout, parsed by hand in
/// OffersRepository — see CatalogApi's docblock for why. No file upload
/// involved (an Offer is just terms on an existing product), so `store`
/// is a plain retrofit call, unlike UpdatesApi/ShowcasesApi.
@RestApi()
abstract class OffersApi {
  factory OffersApi(Dio dio, {String baseUrl}) = _OffersApi;

  @GET('/offers')
  Future<dynamic> offers(@Queries() Map<String, dynamic> query);

  @POST('/offers')
  Future<dynamic> store(@Body() Map<String, dynamic> body);

  @DELETE('/offers/{id}')
  Future<void> destroy(@Path('id') int id);
}
