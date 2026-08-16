import '../../core/network/dio_client.dart';
import '../api/feed_api.dart';
import '../models/comment.dart';
import '../models/feed_item.dart';
import '../models/paginated_result.dart';
import '../models/product.dart';

/// The composed "For You" feed and its comments (CLAUDE.md Part 3). No
/// offline cache here — see the equivalent Part 3 decision for
/// Updates/Offers/Showcases in DECISIONS.md: this content is inherently
/// time-sensitive (an Offer's countdown, a Showcase's freshness), so a
/// stale cached copy would be actively misleading rather than merely old.
class FeedRepository {
  FeedRepository({required FeedApi api}) : _api = api;

  final FeedApi _api;

  Future<PaginatedResult<FeedItem>> feed({double? lat, double? lng, int page = 1}) async {
    try {
      final json = await _api.feed({'lat': ?lat, 'lng': ?lng, 'page': page});
      return PaginatedResult<FeedItem>.fromJson(json as Map<String, dynamic>, FeedItem.fromJson);
    } catch (e) {
      throw mapDioError(e);
    }
  }

  Future<PaginatedResult<ProductComment>> comments(int productId, {int page = 1}) async {
    try {
      final json = await _api.comments(productId, page);
      return PaginatedResult<ProductComment>.fromJson(json as Map<String, dynamic>, ProductComment.fromJson);
    } catch (e) {
      throw mapDioError(e);
    }
  }

  Future<ProductComment> postComment(int productId, {required String body, int? parentId}) async {
    try {
      final json = await _api.postComment(productId, {'body': body, 'parent_id': ?parentId});
      return ProductComment.fromJson((json as Map<String, dynamic>)['data'] as Map<String, dynamic>);
    } catch (e) {
      throw mapDioError(e);
    }
  }

  Future<void> deleteComment(int commentId) async {
    try {
      await _api.deleteComment(commentId);
    } catch (e) {
      throw mapDioError(e);
    }
  }

  Future<Product> boostProduct(
    int productId, {
    required bool isSponsored,
    int? durationDays,
    String? contactMethod,
  }) async {
    try {
      final json = await _api.boostProduct(productId, {
        'is_sponsored': isSponsored,
        'duration_days': ?durationDays,
        'contact_method': ?contactMethod,
      });
      return Product.fromJson((json as Map<String, dynamic>)['data'] as Map<String, dynamic>);
    } catch (e) {
      throw mapDioError(e);
    }
  }
}
