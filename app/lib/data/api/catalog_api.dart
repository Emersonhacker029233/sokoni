import 'package:dio/dio.dart' hide Headers;
import 'package:retrofit/retrofit.dart';

part 'catalog_api.g.dart';

/// Categories + products + favourites — the discovery domain. Return types
/// are `dynamic` throughout (not `Map<String, dynamic>`/typed models):
/// retrofit_generator mis-generates a `dynamic.fromJson(...)` call for
/// `Map<String, dynamic>` return types (see DECISIONS.md), so parsing is
/// done by hand in ProductRepository instead.
@RestApi()
abstract class CatalogApi {
  factory CatalogApi(Dio dio, {String baseUrl}) = _CatalogApi;

  @GET('/categories')
  Future<dynamic> categories();

  @GET('/products')
  Future<dynamic> products(@Queries() Map<String, dynamic> query);

  @GET('/products/{id}')
  Future<dynamic> product(@Path('id') int id);

  @GET('/favorites')
  Future<dynamic> favorites();

  @POST('/products/{id}/favorite')
  Future<void> favorite(@Path('id') int id);

  @DELETE('/products/{id}/favorite')
  Future<void> unfavorite(@Path('id') int id);

  @GET('/shop/products')
  Future<dynamic> myProducts(@Query('page') int page);

  @POST('/products')
  Future<dynamic> createProduct(@Body() Map<String, dynamic> body);

  @PATCH('/products/{id}')
  Future<dynamic> updateProduct(@Path('id') int id, @Body() Map<String, dynamic> body);

  @DELETE('/products/{id}')
  Future<void> deleteProduct(@Path('id') int id);

  @DELETE('/products/{productId}/media/{mediaId}')
  Future<void> deleteMedia(@Path('productId') int productId, @Path('mediaId') int mediaId);
}
