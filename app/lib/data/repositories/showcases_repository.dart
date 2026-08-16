import 'package:dio/dio.dart';

import '../../core/network/dio_client.dart';
import '../api/showcases_api.dart';
import '../models/paginated_result.dart';
import '../models/showcase.dart';

class ShowcasesRepository {
  ShowcasesRepository({required ShowcasesApi api, required Dio dio}) : _api = api, _dio = dio;

  final ShowcasesApi _api;
  final Dio _dio;

  Future<PaginatedResult<Showcase>> showcases({bool following = false, int? sellerId, int page = 1}) async {
    try {
      final json = await _api.showcases({
        'following': ?following ? 1 : null,
        'seller_id': ?sellerId,
        'page': page,
      });
      return PaginatedResult<Showcase>.fromJson(json as Map<String, dynamic>, Showcase.fromJson);
    } catch (e) {
      throw mapDioError(e);
    }
  }

  Future<Showcase> show(int id) async {
    try {
      final json = await _api.show(id);
      return Showcase.fromJson((json as Map<String, dynamic>)['data'] as Map<String, dynamic>);
    } catch (e) {
      throw mapDioError(e);
    }
  }

  Future<Showcase> create({
    required int productId,
    required String videoPath,
    required String thumbnailPath,
    required int durationSeconds,
    String? caption,
  }) async {
    try {
      final response = await _dio.post(
        '/showcases',
        data: FormData.fromMap({
          'product_id': productId,
          'file': await MultipartFile.fromFile(videoPath),
          'thumbnail': await MultipartFile.fromFile(thumbnailPath),
          'duration': durationSeconds,
          'caption': ?caption,
        }),
      );
      return Showcase.fromJson((response.data as Map<String, dynamic>)['data'] as Map<String, dynamic>);
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
