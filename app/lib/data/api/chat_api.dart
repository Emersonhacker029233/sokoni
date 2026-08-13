import 'package:dio/dio.dart' hide Headers;
import 'package:retrofit/retrofit.dart';

part 'chat_api.g.dart';

/// Return types are `dynamic` throughout — see `CatalogApi`'s docblock
/// (retrofit_generator codegen bug on `Map<String, dynamic>` returns).
@RestApi()
abstract class ChatApi {
  factory ChatApi(Dio dio, {String baseUrl}) = _ChatApi;

  @GET('/conversations')
  Future<dynamic> conversations(@Query('page') int page);

  @GET('/conversations/{id}')
  Future<dynamic> conversation(@Path('id') int id);

  @POST('/conversations')
  Future<dynamic> startConversation(@Body() Map<String, dynamic> body);

  @PATCH('/conversations/{id}/typing')
  Future<dynamic> sendTyping(@Path('id') int id);

  @GET('/conversations/{id}/messages')
  Future<dynamic> messages(@Path('id') int id, @Query('page') int page);
}
