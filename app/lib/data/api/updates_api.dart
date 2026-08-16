import 'package:dio/dio.dart' hide Headers;
import 'package:retrofit/retrofit.dart';

part 'updates_api.g.dart';

/// Return types are `dynamic` throughout, parsed by hand in
/// UpdatesRepository — see CatalogApi's docblock for why.
///
/// `store`/media upload isn't here — same reasoning as SellerRepository's
/// onboarding uploads: retrofit's `@MultiPart()`/`@Part()` annotations add
/// real ceremony for one call site, so posting an Update goes through a
/// plain Dio `FormData` call in the repository instead, same as product media.
@RestApi()
abstract class UpdatesApi {
  factory UpdatesApi(Dio dio, {String baseUrl}) = _UpdatesApi;

  @GET('/updates')
  Future<dynamic> updates(@Queries() Map<String, dynamic> query);

  @DELETE('/updates/{id}')
  Future<void> destroy(@Path('id') int id);
}
