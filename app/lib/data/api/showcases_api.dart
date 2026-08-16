import 'package:dio/dio.dart' hide Headers;
import 'package:retrofit/retrofit.dart';

part 'showcases_api.g.dart';

/// Return types are `dynamic` throughout, parsed by hand in
/// ShowcasesRepository — see CatalogApi's docblock for why. `store` isn't
/// here — same reasoning as UpdatesApi (a plain Dio multipart call instead).
@RestApi()
abstract class ShowcasesApi {
  factory ShowcasesApi(Dio dio, {String baseUrl}) = _ShowcasesApi;

  @GET('/showcases')
  Future<dynamic> showcases(@Queries() Map<String, dynamic> query);

  @GET('/showcases/{id}')
  Future<dynamic> show(@Path('id') int id);

  @DELETE('/showcases/{id}')
  Future<void> destroy(@Path('id') int id);
}
