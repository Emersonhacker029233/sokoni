import '../../core/network/dio_client.dart';
import '../api/offers_api.dart';
import '../models/offer.dart';
import '../models/paginated_result.dart';

class OffersRepository {
  OffersRepository({required OffersApi api}) : _api = api;

  final OffersApi _api;

  Future<PaginatedResult<Offer>> offers({bool following = false, int? sellerId, int page = 1}) async {
    try {
      final json = await _api.offers({
        'following': ?following ? 1 : null,
        'seller_id': ?sellerId,
        'page': page,
      });
      return PaginatedResult<Offer>.fromJson(json as Map<String, dynamic>, Offer.fromJson);
    } catch (e) {
      throw mapDioError(e);
    }
  }

  Future<Offer> create({
    required int productId,
    required String discountType,
    required num discountValue,
    required int durationDays,
  }) async {
    try {
      final json = await _api.store({
        'product_id': productId,
        'discount_type': discountType,
        'discount_value': discountValue,
        'duration_days': durationDays,
      });
      return Offer.fromJson((json as Map<String, dynamic>)['data'] as Map<String, dynamic>);
    } catch (e) {
      throw mapDioError(e);
    }
  }

  Future<void> delete(int id) async {
    try {
      await _api.destroy(id);
    } catch (e) {
      throw mapDioError(e);
    }
  }
}
