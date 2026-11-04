import 'package:dio/dio.dart';

import '../../core/network/dio_client.dart';
import '../api/delivery_api.dart';
import '../models/delivery.dart';

/// Sokoni 1.1 delivery flow. Errors come back as either a
/// [DeliveryActionError] (the server refused with a stable `code`, e.g.
/// `payment_in_progress`, `too_far`) or the usual [ApiException] from
/// [mapDioError] (offline, timeout, validation, ...).
class DeliveryRepository {
  DeliveryRepository({required DeliveryApi api}) : _api = api;

  final DeliveryApi _api;

  /// Null when the server has delivery switched off (the route 404s), so
  /// the app keeps the Sokoni 1.0 checkout. Other failures throw.
  Future<DeliveryConfig?> config() async {
    try {
      final config = DeliveryConfig.fromJson(_data(await _api.config()));
      return config.enabled ? config : null;
    } on DioException catch (e) {
      if (e.response?.statusCode == 404) return null;
      throw _error(e);
    } catch (e) {
      throw _error(e);
    }
  }

  Future<DeliveryQuote> quote({required int sellerId, required double lat, required double lng}) async {
    try {
      return DeliveryQuote.fromJson(_data(await _api.quote(sellerId: sellerId, lat: lat, lng: lng)));
    } catch (e) {
      throw _error(e);
    }
  }

  /// Null while the shop hasn't accepted yet (no delivery job exists).
  Future<DeliveryTracking?> tracking(int orderId) async {
    try {
      return DeliveryTracking.fromJson(_data(await _api.tracking(orderId)));
    } catch (e) {
      final error = _error(e);
      if (error is DeliveryActionError && error.code == 'no_delivery_job') return null;
      throw error;
    }
  }

  Future<DeliveryTracking> pay(int orderId, {required String channel, required String msisdn, required String idempotencyKey}) =>
      _view(() => _api.pay(orderId, channel: channel, msisdn: msisdn, idempotencyKey: idempotencyKey));

  Future<DeliveryTracking> cancel(int orderId, {String? reason}) => _view(() => _api.cancel(orderId, reason: reason));

  Future<DeliveryTracking> retry(int orderId) => _view(() => _api.retry(orderId));

  Future<DeliveryTracking> switchToPickup(int orderId) => _view(() => _api.switchToPickup(orderId));

  Future<void> rate(int orderId, {required int rating, String? comment}) async {
    try {
      await _api.rate(orderId, rating: rating, comment: comment);
    } catch (e) {
      throw _error(e);
    }
  }

  /// Firebase credentials for the live map, or null when the server says
  /// to poll instead (503 `fallback: poll`, or no active job).
  Future<RealtimeCredentials?> realtimeToken() async {
    try {
      final data = _data(await _api.realtimeToken());
      final token = data['token'];
      final url = data['database_url'];
      if (token is! String || url is! String || url.isEmpty) return null;
      return RealtimeCredentials(token: token, databaseUrl: url);
    } on DioException catch (e) {
      final status = e.response?.statusCode;
      if (status == 503 || status == 403) return null;
      throw _error(e);
    } catch (e) {
      throw _error(e);
    }
  }

  Future<DeliveryTracking> _view(Future<dynamic> Function() call) async {
    try {
      return DeliveryTracking.fromJson(_data(await call()));
    } catch (e) {
      throw _error(e);
    }
  }

  static Map<String, dynamic> _data(dynamic body) {
    if (body is Map && body['data'] is Map) return (body['data'] as Map).cast<String, dynamic>();
    throw const FormatException('Unexpected delivery response');
  }

  /// A refusal carrying a `code` becomes a [DeliveryActionError]; anything
  /// else goes through the app-wide [mapDioError].
  static Object _error(Object e) {
    if (e is DeliveryActionError) return e;
    if (e is DioException) {
      final data = e.response?.data;
      if (data is Map && data['code'] is String) {
        return DeliveryActionError(
          code: data['code'] as String,
          message: data['message'] is String ? data['message'] as String : data['code'] as String,
          statusCode: e.response?.statusCode,
        );
      }
    }
    return mapDioError(e);
  }
}
