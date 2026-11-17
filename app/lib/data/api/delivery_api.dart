import 'package:dio/dio.dart';

/// Bolt delivery and returns endpoints (docs/delivery/BOLT.md, RETURNS.md).
///
/// A thin, hand-written Dio wrapper rather than a retrofit interface: every
/// call here returns the raw decoded body for [DeliveryRepository] to parse,
/// the same contract the retrofit APIs follow, without a codegen step.
class DeliveryApi {
  DeliveryApi(this._dio);

  final Dio _dio;

  Future<dynamic> features() async => (await _dio.get<dynamic>('/features')).data;

  Future<dynamic> config() async => (await _dio.get<dynamic>('/delivery/config')).data;

  Future<dynamic> quote({required int sellerId, required double lat, required double lng}) async =>
      (await _dio.get<dynamic>('/delivery/quote', queryParameters: {'seller_id': sellerId, 'lat': lat, 'lng': lng})).data;

  Future<dynamic> tracking(int orderId) async => (await _dio.get<dynamic>('/delivery/orders/$orderId')).data;

  Future<dynamic> pay(int orderId, {required String channel, required String msisdn, required String idempotencyKey}) async =>
      (await _dio.post<dynamic>(
        '/delivery/orders/$orderId/pay',
        data: {'channel': channel, 'msisdn': msisdn, 'idempotency_key': idempotencyKey},
      )).data;

  Future<dynamic> cancel(int orderId, {String? reason}) async =>
      (await _dio.post<dynamic>('/delivery/orders/$orderId/cancel', data: {'reason': ?reason})).data;

  Future<dynamic> retry(int orderId) async => (await _dio.post<dynamic>('/delivery/orders/$orderId/retry')).data;

  Future<dynamic> switchToPickup(int orderId) async =>
      (await _dio.post<dynamic>('/delivery/orders/$orderId/switch-to-pickup')).data;

  // Returns.

  Future<dynamic> requestReturn(int orderId, {required String reason, String? comment, required List<MultipartFile> photos}) async =>
      (await _dio.post<dynamic>(
        '/orders/$orderId/returns',
        data: FormData.fromMap({'reason': reason, 'comment': ?comment, 'photos[]': photos}),
      )).data;

  Future<dynamic> returnRequest(int returnId) async => (await _dio.get<dynamic>('/returns/$returnId')).data;

  Future<dynamic> returnAction(int returnId, String action, [Map<String, dynamic>? body]) async =>
      (await _dio.post<dynamic>('/returns/$returnId/$action', data: body)).data;

  Future<dynamic> acceptReturnsTerms(String version) async =>
      (await _dio.post<dynamic>('/returns/terms/accept', data: {'version': version})).data;

  Future<dynamic> updateReturnPolicy({required String policy, String? notes}) async =>
      (await _dio.put<dynamic>('/shop/return-policy', data: {'return_policy': policy, 'return_notes': ?notes})).data;
}
