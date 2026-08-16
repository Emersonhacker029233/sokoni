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

  /// "Customer"/"Mteja" follow relationship (CLAUDE.md Part 3).
  @GET('/customers')
  Future<dynamic> following(@Query('page') int page);

  @POST('/sellers/{handle}/follow')
  Future<dynamic> follow(@Path('handle') String handle);

  @DELETE('/sellers/{handle}/follow')
  Future<void> unfollow(@Path('handle') String handle);

  /// Owner-only dashboard strip (CLAUDE.md Part 4).
  @GET('/sellers/{id}/dashboard')
  Future<dynamic> dashboard(@Path('id') int id);

  /// Shop profile edit (CLAUDE.md Part 4's "Edit profile" button) —
  /// bio/WhatsApp/its visibility toggle only; shop name/handle/category
  /// are set once at onboarding and have no edit surface, matching how
  /// the rest of this app treats identity fields set during onboarding.
  @PATCH('/sellers/{id}')
  Future<dynamic> updateProfile(@Path('id') int id, @Body() Map<String, dynamic> body);
}
