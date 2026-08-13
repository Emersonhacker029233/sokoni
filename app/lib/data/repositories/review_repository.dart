import '../../core/network/dio_client.dart';
import '../api/review_api.dart';
import '../models/review.dart';

class ReviewRepository {
  ReviewRepository({required ReviewApi api}) : _api = api;

  final ReviewApi _api;

  Future<Review> submit({required int orderId, required int rating, String? comment}) async {
    try {
      final json = await _api.submit(orderId, {'rating': rating, 'comment': ?comment});
      return Review.fromJson((json as Map<String, dynamic>)['data'] as Map<String, dynamic>);
    } catch (e) {
      throw mapDioError(e);
    }
  }

  Future<Review> reply({required int reviewId, required String reply}) async {
    try {
      final json = await _api.reply(reviewId, {'reply': reply});
      return Review.fromJson((json as Map<String, dynamic>)['data'] as Map<String, dynamic>);
    } catch (e) {
      throw mapDioError(e);
    }
  }
}
