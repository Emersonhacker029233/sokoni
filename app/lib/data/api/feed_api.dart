import 'package:dio/dio.dart' hide Headers;
import 'package:retrofit/retrofit.dart';

part 'feed_api.g.dart';

/// The composed "For You" feed (CLAUDE.md Part 3). `dynamic`, not
/// `Map<String, dynamic>` — see `catalog_api.dart`'s docblock for why.
@RestApi()
abstract class FeedApi {
  factory FeedApi(Dio dio, {String baseUrl}) = _FeedApi;

  @GET('/feed')
  Future<dynamic> feed(@Queries() Map<String, dynamic> query);

  @GET('/products/{id}/comments')
  Future<dynamic> comments(@Path('id') int productId, @Query('page') int page);

  @POST('/products/{id}/comments')
  Future<dynamic> postComment(@Path('id') int productId, @Body() Map<String, dynamic> body);

  @DELETE('/comments/{id}')
  Future<void> deleteComment(@Path('id') int commentId);

  @PATCH('/products/{id}/boost')
  Future<dynamic> boostProduct(@Path('id') int productId, @Body() Map<String, dynamic> body);
}
