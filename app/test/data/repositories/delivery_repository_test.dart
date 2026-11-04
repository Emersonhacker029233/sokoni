import 'dart:convert';
import 'dart:typed_data';

import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:sokoni/core/network/api_exception.dart';
import 'package:sokoni/data/api/delivery_api.dart';
import 'package:sokoni/data/api/order_api.dart';
import 'package:sokoni/data/models/delivery.dart';
import 'package:sokoni/data/repositories/delivery_repository.dart';
import 'package:sokoni/data/repositories/order_repository.dart';

import '../../fixtures/delivery_fixtures.dart';

/// API contract, client side: the requests Sokoni 1.1 sends and how it
/// reads every answer the server documents (docs/delivery/API.md).
class _Scripted implements HttpClientAdapter {
  _Scripted(this.routes);

  /// "METHOD /path" -> (status, body)
  final Map<String, (int, Object)> routes;
  final List<RequestOptions> requests = [];

  @override
  Future<ResponseBody> fetch(RequestOptions options, Stream<Uint8List>? requestStream, Future<void>? cancelFuture) async {
    requests.add(options);
    final route = routes['${options.method} ${options.path}'];
    if (route == null) throw DioException.connectionError(requestOptions: options, reason: 'unscripted ${options.method} ${options.path}');
    return ResponseBody.fromString(jsonEncode(route.$2), route.$1, headers: {
      Headers.contentTypeHeader: [Headers.jsonContentType],
    });
  }

  @override
  void close({bool force = false}) {}
}

(DeliveryRepository, _Scripted) _repo(Map<String, (int, Object)> routes) {
  final adapter = _Scripted(routes);
  final dio = Dio(BaseOptions(baseUrl: 'https://staging-api.sokoni.co.tz/api'))..httpClientAdapter = adapter;
  return (DeliveryRepository(api: DeliveryApi(dio)), adapter);
}

void main() {
  group('config', () {
    test('delivery on: the 1.1 flow settings', () async {
      final (repo, _) = _repo({'GET /delivery/config': (200, {'data': deliveryConfigJson(mode: 'cash')})});
      final config = await repo.config();
      expect(config, isNotNull);
      expect(config!.feeInCash, isTrue);
      expect(config.unpaidTimeoutMinutes, 20);
      expect(config.channels, contains('mpesa'));
    });

    test('DELIVERY_ENABLED off (404): null, so checkout stays the 1.0 one', () async {
      final (repo, _) = _repo({'GET /delivery/config': (404, {'message': 'Not Found'})});
      expect(await repo.config(), isNull);
    });

    test('offline is an error, not "delivery off"', () async {
      final (repo, _) = _repo({});
      expect(repo.config(), throwsA(isA<NetworkException>()));
    });
  });

  test('quote sends the shop and the pin, and reads too_far as a coded refusal', () async {
    final (repo, adapter) = _repo({
      'GET /delivery/quote': (422, {'code': 'too_far', 'message': 'Delivery is available up to 10 km from the shop. Choose pickup instead.'}),
    });

    final error = await repo.quote(sellerId: 5, lat: -6.79, lng: 39.28).then<Object?>((_) => null, onError: (Object e) => e);
    expect(error, isA<DeliveryActionError>().having((e) => e.code, 'code', 'too_far'));
    expect(adapter.requests.single.queryParameters, {'seller_id': 5, 'lat': -6.79, 'lng': 39.28});
  });

  test('no delivery job yet (the shop has not accepted) reads as null', () async {
    final (repo, _) = _repo({'GET /delivery/orders/345': (404, {'code': 'no_delivery_job', 'message': 'This order has no delivery yet.'})});
    expect(await repo.tracking(345), isNull);
  });

  test('pay sends channel, number and key, never an amount', () async {
    final (repo, adapter) = _repo({
      'POST /delivery/orders/345/pay': (202, {
        'data': buyerView(status: 'pending_payment', attempt: {'id': 1, 'status': 'pending', 'amount': 4500}),
      }),
    });

    final tracking = await repo.pay(345, channel: 'airtel', msisdn: '+255754123456', idempotencyKey: 'abc123def456ghi789jkl012');

    final body = adapter.requests.single.data as Map<String, dynamic>;
    expect(body, {'channel': 'airtel', 'msisdn': '+255754123456', 'idempotency_key': 'abc123def456ghi789jkl012'});
    expect(body.containsKey('amount'), isFalse);
    expect(tracking.payment!.attempt!.isPending, isTrue);
    // Starting a payment never marks it paid: the server's answer says unpaid.
    expect(tracking.paymentStatus, 'unpaid');
  });

  test('a second prompt while one is open is a coded 409', () async {
    final (repo, _) = _repo({
      'POST /delivery/orders/345/pay': (409, {'code': 'payment_in_progress', 'message': 'A payment request is already on your phone.'}),
    });
    final error = await repo.pay(345, channel: 'mpesa', msisdn: '+255754123456', idempotencyKey: 'k' * 24).then<Object?>((_) => null, onError: (Object e) => e);
    expect(error, isA<DeliveryActionError>().having((e) => e.code, 'code', 'payment_in_progress').having((e) => e.statusCode, 'status', 409));
  });

  test('buyer actions hit their documented routes', () async {
    final view = {'data': buyerView(status: 'no_driver_found')};
    final (repo, adapter) = _repo({
      'POST /delivery/orders/345/retry': (200, view),
      'POST /delivery/orders/345/switch-to-pickup': (200, view),
      'POST /delivery/orders/345/cancel': (200, view),
      'POST /delivery/orders/345/rating': (201, {'message': 'Thanks for rating.'}),
    });

    await repo.retry(345);
    await repo.switchToPickup(345);
    await repo.cancel(345, reason: 'Changed my mind');
    await repo.rate(345, rating: 5, comment: 'Fast');

    expect(adapter.requests.map((r) => r.path), [
      '/delivery/orders/345/retry',
      '/delivery/orders/345/switch-to-pickup',
      '/delivery/orders/345/cancel',
      '/delivery/orders/345/rating',
    ]);
    expect(adapter.requests[2].data, {'reason': 'Changed my mind'});
    expect(adapter.requests[3].data, {'rating': 5, 'comment': 'Fast'});
  });

  test('no Firebase on the server (503 fallback: poll) means poll, not an error', () async {
    final (repo, _) = _repo({'POST /delivery/realtime-token': (503, {'code': 'tracking_unavailable', 'fallback': 'poll'})});
    expect(await repo.realtimeToken(), isNull);
  });

  test('realtime credentials are read when Firebase is configured', () async {
    final (repo, _) = _repo({
      'POST /delivery/realtime-token': (200, {'data': {'token': 'jwt', 'uid': 'u1', 'database_url': 'https://x.firebaseio.com', 'expires_in': 3600}}),
    });
    final credentials = await repo.realtimeToken();
    expect(credentials!.token, 'jwt');
    expect(credentials.databaseUrl, 'https://x.firebaseio.com');
  });

  test('dispatch checkout opts in with fulfilment and the rider phone', () async {
    final adapter = _Scripted({
      'POST /orders': (201, {
        'data': {
          'id': 345, 'code': 'SKAB12CD34', 'status': 'pending', 'subtotal': 20000, 'delivery_fee': 0, 'total': 20000,
          'delivery_method': 'delivery', 'fulfilment': 'dispatch', 'delivery_phone': '+255765000123',
          'payment_method': 'cash_on_delivery', 'timeline': <Object>[],
        },
      }),
    });
    final dio = Dio(BaseOptions(baseUrl: 'https://staging-api.sokoni.co.tz/api'))..httpClientAdapter = adapter;

    final order = await OrderRepository(api: OrderApi(dio)).checkout(
      items: [(productId: 9, qty: 1)],
      deliveryMethod: 'delivery',
      fulfilment: 'dispatch',
      address: 'Mtaa wa Uhuru 12',
      deliveryLat: -6.79,
      deliveryLng: 39.28,
      deliveryPhone: '+255765000123',
      paymentMethod: 'cash_on_delivery',
    );

    final body = adapter.requests.single.data as Map<String, dynamic>;
    expect(body['fulfilment'], 'dispatch');
    expect(body['delivery_phone'], '+255765000123');
    expect(body['delivery_lat'], -6.79);
    expect(order.fulfilment, 'dispatch');
    expect(order.deliveryPhone, '+255765000123');
  });

  test('a 1.0-style checkout sends neither new field', () async {
    final adapter = _Scripted({
      'POST /orders': (201, {
        'data': {
          'id': 1, 'code': 'SK1', 'status': 'pending', 'subtotal': 20000, 'delivery_fee': 3000, 'total': 23000,
          'delivery_method': 'delivery', 'payment_method': 'cash_on_delivery', 'timeline': <Object>[],
        },
      }),
    });
    final dio = Dio(BaseOptions(baseUrl: 'https://x/api'))..httpClientAdapter = adapter;

    await OrderRepository(api: OrderApi(dio)).checkout(
      items: [(productId: 9, qty: 1)],
      deliveryMethod: 'delivery',
      address: 'Somewhere',
      paymentMethod: 'cash_on_delivery',
    );

    final body = adapter.requests.single.data as Map<String, dynamic>;
    expect(body.containsKey('fulfilment'), isFalse);
    expect(body.containsKey('delivery_phone'), isFalse);
  });
}
