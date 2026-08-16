import 'package:dio/dio.dart';

import '../../core/network/dio_client.dart';
import '../api/updates_api.dart';
import '../models/paginated_result.dart';
import '../models/social_update.dart';

class UpdatesRepository {
  UpdatesRepository({required UpdatesApi api, required Dio dio}) : _api = api, _dio = dio;

  final UpdatesApi _api;
  final Dio _dio;

  Future<PaginatedResult<SocialUpdate>> updates({bool following = false, int? sellerId, int page = 1}) async {
    try {
      final json = await _api.updates({
        'following': ?following ? 1 : null,
        'seller_id': ?sellerId,
        'page': page,
      });
      return PaginatedResult<SocialUpdate>.fromJson(json as Map<String, dynamic>, SocialUpdate.fromJson);
    } catch (e) {
      throw mapDioError(e);
    }
  }

  Future<SocialUpdate> postImage({required String imagePath, String? caption, int? productId}) async {
    try {
      final response = await _dio.post(
        '/updates',
        data: FormData.fromMap({
          'type': 'image',
          'file': await MultipartFile.fromFile(imagePath),
          'caption': ?caption,
          'product_id': ?productId,
        }),
      );
      return SocialUpdate.fromJson((response.data as Map<String, dynamic>)['data'] as Map<String, dynamic>);
    } catch (e) {
      throw mapDioError(e);
    }
  }

  Future<SocialUpdate> postVideo({
    required String videoPath,
    required String thumbnailPath,
    String? caption,
    int? productId,
  }) async {
    try {
      final response = await _dio.post(
        '/updates',
        data: FormData.fromMap({
          'type': 'video',
          'file': await MultipartFile.fromFile(videoPath),
          'thumbnail': await MultipartFile.fromFile(thumbnailPath),
          'caption': ?caption,
          'product_id': ?productId,
        }),
      );
      return SocialUpdate.fromJson((response.data as Map<String, dynamic>)['data'] as Map<String, dynamic>);
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
