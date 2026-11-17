import 'package:dio/dio.dart';

import '../../core/network/dio_client.dart';
import '../api/report_api.dart';
import '../models/blocked_account.dart';

/// Report and block (Apple Guideline 1.2). Reports reach the moderators'
/// queue and alert them at once; a block also files one, so the
/// moderators hear about every block too (server side, BlockController).
class ModerationRepository {
  ModerationRepository({required Dio dio, required ReportApi reportApi})
    : _dio = dio,
      _reportApi = reportApi;

  final Dio _dio;
  final ReportApi _reportApi;

  /// [type] is one of the API's report types: product, shop, message,
  /// comment, review, showcase, update, offer, user. [reason] is a code
  /// the server knows (`abusive`, `spam`, ...), never a translated label.
  Future<void> report({required String type, required int id, required String reason, String? note}) async {
    try {
      await _reportApi.submit({
        'reportable_type': type,
        'reportable_id': id,
        'reason': reason,
        if (note != null && note.trim().isNotEmpty) 'note': note.trim(),
      });
    } catch (e) {
      throw mapDioError(e);
    }
  }

  Future<List<BlockedAccount>> blocks() async {
    try {
      final response = await _dio.get<Map<String, dynamic>>('/blocks');
      final rows = response.data?['data'] as List? ?? const [];
      return rows.map((row) => BlockedAccount.fromJson(row as Map<String, dynamic>)).toList();
    } catch (e) {
      throw mapDioError(e);
    }
  }

  /// Blocks a person ([userId]) or the owner of a shop ([sellerId]).
  Future<BlockedAccount> block({int? userId, int? sellerId, String? reason}) async {
    assert((userId == null) != (sellerId == null), 'Pass exactly one of userId and sellerId');
    try {
      final response = await _dio.post<Map<String, dynamic>>(
        '/blocks',
        data: {'user_id': ?userId, 'seller_id': ?sellerId, 'reason': ?reason},
      );
      return BlockedAccount.fromJson(response.data!['data'] as Map<String, dynamic>);
    } catch (e) {
      throw mapDioError(e);
    }
  }

  Future<void> unblock(int userId) async {
    try {
      await _dio.delete<void>('/blocks/$userId');
    } catch (e) {
      throw mapDioError(e);
    }
  }
}
