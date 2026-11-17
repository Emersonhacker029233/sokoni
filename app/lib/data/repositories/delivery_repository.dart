import 'package:dio/dio.dart';

import '../../core/network/dio_client.dart';
import '../api/delivery_api.dart';
import '../models/delivery.dart';

/// Bolt delivery and returns. Errors come back as either a
/// [DeliveryActionError] (the server refused with a stable `code`, e.g.
/// `payment_in_progress`, `area_not_serviced`, `return_window_closed`) or
/// the usual [ApiException] from [mapDioError] (offline, timeout,
/// validation, ...).
class DeliveryRepository {
  DeliveryRepository({required DeliveryApi api}) : _api = api;

  final DeliveryApi _api;

  /// What this user may see. Any failure means "off": a server that
  /// predates the dark launch has no such route, and the app must then
  /// look exactly like the build before it.
  Future<SokoniFeatures> features() async {
    try {
      return SokoniFeatures.fromJson(_data(await _api.features()));
    } catch (_) {
      return SokoniFeatures.off;
    }
  }

  /// Null when delivery is not available to this user (the route 404s).
  /// Other failures throw.
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

  // Returns.

  /// [photos] are local file paths (1-3), already compressed by the picker.
  Future<ReturnRequestInfo> requestReturn(int orderId, {required String reason, String? comment, required List<String> photos}) async {
    try {
      final files = [for (final path in photos) await MultipartFile.fromFile(path)];
      return ReturnRequestInfo.fromJson(_data(await _api.requestReturn(orderId, reason: reason, comment: comment, photos: files)));
    } catch (e) {
      throw _error(e);
    }
  }

  Future<ReturnRequestInfo> returnRequest(int returnId) => _return(() => _api.returnRequest(returnId));

  /// accept | reject | dispute | withdraw | book | refunded | pay
  Future<ReturnRequestInfo> returnAction(int returnId, String action, [Map<String, dynamic>? body]) =>
      _return(() => _api.returnAction(returnId, action, body));

  Future<void> acceptReturnsTerms(String version) async {
    try {
      await _api.acceptReturnsTerms(version);
    } catch (e) {
      throw _error(e);
    }
  }

  Future<ReturnPolicyInfo> updateReturnPolicy({required String policy, String? notes}) async {
    try {
      return ReturnPolicyInfo.fromJson(_data(await _api.updateReturnPolicy(policy: policy, notes: notes)))!;
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

  Future<ReturnRequestInfo> _return(Future<dynamic> Function() call) async {
    try {
      return ReturnRequestInfo.fromJson(_data(await call()));
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
