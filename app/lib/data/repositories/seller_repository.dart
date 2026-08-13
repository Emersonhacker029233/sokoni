import '../../core/network/dio_client.dart';
import '../api/seller_api.dart';
import '../models/paginated_result.dart';
import '../models/review.dart';
import '../models/seller_profile.dart';

class SellerRepository {
  SellerRepository({required SellerApi api}) : _api = api;

  final SellerApi _api;

  Future<SellerProfile> byHandle(String handle) async {
    try {
      final json = await _api.show(handle);
      return SellerProfile.fromJson(json['data'] as Map<String, dynamic>);
    } catch (e) {
      throw mapDioError(e);
    }
  }

  Future<List<SellerProfile>> browse({double? lat, double? lng, double? radiusKm}) async {
    try {
      final json = await _api.sellers({
        'lat': ?lat,
        'lng': ?lng,
        'radius_km': ?radiusKm,
      });
      final list = (json['data'] as List).cast<Map<String, dynamic>>();
      return list.map(SellerProfile.fromJson).toList();
    } catch (e) {
      throw mapDioError(e);
    }
  }

  Future<(PaginatedResult<Review> page, Map<String, int> distribution)> reviews(
    String handle, {
    int page = 1,
  }) async {
    try {
      final json = await _api.reviews(handle, page) as Map<String, dynamic>;
      final result = PaginatedResult<Review>.fromJson(json, Review.fromJson);
      final distribution = <String, int>{};
      final rawDistribution = json['meta']?['distribution'] as Map<String, dynamic>?;
      rawDistribution?.forEach((rating, count) => distribution[rating] = count as int);
      return (result, distribution);
    } catch (e) {
      throw mapDioError(e);
    }
  }

  Future<int> startConversation({required int sellerId, int? productId}) async {
    try {
      final json = await _api.startConversation({
        'seller_id': sellerId,
        'product_id': ?productId,
      });
      return (json as Map<String, dynamic>)['data']['id'] as int;
    } catch (e) {
      throw mapDioError(e);
    }
  }
}
