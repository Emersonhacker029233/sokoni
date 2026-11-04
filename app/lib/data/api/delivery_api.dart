import 'package:dio/dio.dart';

/// Sokoni 1.1 customer-app delivery endpoints (docs/delivery/API.md).
///
/// A thin, hand-written Dio wrapper rather than a retrofit interface: every
/// call here returns the raw decoded body for [DeliveryRepository] to parse,
/// the same contract the retrofit APIs follow, without a codegen step.
class DeliveryApi {
  DeliveryApi(this._dio);

  final Dio _dio;

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

  Future<dynamic> rate(int orderId, {required int rating, String? comment}) async =>
      (await _dio.post<dynamic>('/delivery/orders/$orderId/rating', data: {'rating': rating, 'comment': ?comment})).data;

  Future<dynamic> realtimeToken() async => (await _dio.post<dynamic>('/delivery/realtime-token')).data;
}
