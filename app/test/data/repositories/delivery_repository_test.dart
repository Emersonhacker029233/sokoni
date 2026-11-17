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

/// API contract, client side: the requests the app sends and how it reads
/// every answer the server documents (docs/delivery/BOLT.md, RETURNS.md).
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
  group('features (the dark launch gate)', () {
    test('reads what this user may see', () async {
      final (repo, _) = _repo({'GET /features': (200, {'data': {'delivery_enabled': true, 'returns_enabled': true, 'returns_terms_version': '1.2', 'returns_terms_accepted': true}})});
      final f = await repo.features();
      expect(f.deliveryEnabled, isTrue);
      expect(f.returnsEnabled, isTrue);
    });

    test('an older server (404) or no connection means everything off', () async {
      final (old, _) = _repo({'GET /features': (404, {'message': 'Not Found'})});
      expect((await old.features()).deliveryEnabled, isFalse);
      final (offline, _) = _repo({});
      expect((await offline.features()).returnsEnabled, isFalse);
    });
  });

  group('config', () {
    test('delivery on: Bolt, paid in the app', () async {
      final (repo, _) = _repo({'GET /delivery/config': (200, {'data': deliveryConfigJson()})});
      final config = await repo.config();
      expect(config, isNotNull);
      expect(config!.unpaidTimeoutMinutes, 20);
      expect(config.pollSeconds, 10);
      expect(config.channels, contains('mpesa'));
      expect(config.returnsEnabled, isTrue);
    });

    test('delivery off for this user (404): null, so checkout stays the ordinary one', () async {
      final (repo, _) = _repo({'GET /delivery/config': (404, {'message': 'Not Found'})});
      expect(await repo.config(), isNull);
    });

    test('offline is an error, not "delivery off"', () async {
      final (repo, _) = _repo({});
      expect(repo.config(), throwsA(isA<NetworkException>()));
    });
  });

  test('quote sends the shop and the pin, and reads Bolt refusals as codes', () async {
    final (repo, adapter) = _repo({
      'GET /delivery/quote': (422, {'code': 'area_not_serviced', 'message': 'Bolt does not deliver in this area yet. Choose pickup instead.'}),
    });

    final error = await repo.quote(sellerId: 5, lat: -6.79, lng: 39.28).then<Object?>((_) => null, onError: (Object e) => e);
    expect(error, isA<DeliveryActionError>().having((e) => e.code, 'code', 'area_not_serviced'));
    expect(adapter.requests.single.queryParameters, {'seller_id': 5, 'lat': -6.79, 'lng': 39.28});
  });

  test('no delivery job yet (the shop has not accepted) reads as null', () async {
    final (repo, _) = _repo({'GET /delivery/orders/345': (404, {'code': 'no_delivery_job', 'message': 'This order has no delivery yet.'})});
    expect(await repo.tracking(345), isNull);
  });

  test('pay sends channel, number and key, never an amount', () async {
    final (repo, adapter) = _repo({
      'POST /delivery/orders/345/pay': (202, {
        'data': buyerView(status: 'pending_payment', attempt: {'id': 1, 'status': 'pending', 'amount': 6400}),
      }),
    });

    final tracking = await repo.pay(345, channel: 'airtel', msisdn: '+255754123456', idempotencyKey: 'abc123def456ghi789jkl012');

    final body = adapter.requests.single.data as Map<String, dynamic>;
    expect(body, {'channel': 'airtel', 'msisdn': '+255754123456', 'idempotency_key': 'abc123def456ghi789jkl012'});
    expect(body.containsKey('amount'), isFalse);
    expect(tracking.payment!.attempt!.isPending, isTrue);
    expect(tracking.paymentStatus, 'unpaid');
  });

  test('a second prompt while one is open is a coded 409', () async {
    final (repo, _) = _repo({
      'POST /delivery/orders/345/pay': (409, {'code': 'payment_in_progress', 'message': 'A payment request is already on your phone.'}),
    });
    final error = await repo.pay(345, channel: 'mpesa', msisdn: '+255754123456', idempotencyKey: 'k' * 24).then<Object?>((_) => null, onError: (Object e) => e);
    expect(error, isA<DeliveryActionError>().having((e) => e.code, 'code', 'payment_in_progress').having((e) => e.statusCode, 'status', 409));
  });

  test('buyer delivery actions hit their documented routes', () async {
    final view = {'data': buyerView(status: 'no_driver_found')};
    final (repo, adapter) = _repo({
      'POST /delivery/orders/345/retry': (200, view),
      'POST /delivery/orders/345/switch-to-pickup': (200, view),
      'POST /delivery/orders/345/cancel': (200, view),
    });

    await repo.retry(345);
    await repo.switchToPickup(345);
    await repo.cancel(345, reason: 'Changed my mind');

    expect(adapter.requests.map((r) => r.path), ['/delivery/orders/345/retry', '/delivery/orders/345/switch-to-pickup', '/delivery/orders/345/cancel']);
    expect(adapter.requests[2].data, {'reason': 'Changed my mind'});
  });

  group('returns', () {
    test('actions post to /returns/{id}/{action} and read the request back', () async {
      final (repo, adapter) = _repo({
        'POST /returns/7/reject': (200, {'data': returnRequestJson(status: 'rejected')}),
        'POST /returns/7/book': (200, {'data': returnRequestJson(status: 'return_in_transit')}),
      });

      final rejected = await repo.returnAction(7, 'reject', {'reason': 'As described'});
      final booked = await repo.returnAction(7, 'book');

      expect(rejected.status, 'rejected');
      expect(booked.status, 'return_in_transit');
      expect(adapter.requests.first.data, {'reason': 'As described'});
    });

    test('a closed window is a coded refusal the screen can show', () async {
      final (repo, _) = _repo({
        'POST /orders/345/returns': (409, {'code': 'return_window_closed', 'message': 'The return window for this order has closed.'}),
      });
      final error = await repo.requestReturn(345, reason: 'damaged', photos: const []).then<Object?>((_) => null, onError: (Object e) => e);
      expect(error, isA<DeliveryActionError>().having((e) => e.code, 'code', 'return_window_closed'));
    });

    test('the shop return policy and the returns Terms', () async {
      final (repo, adapter) = _repo({
        'PUT /shop/return-policy': (200, {'data': {'code': '3d', 'hours': 72, 'label': 'Returns within 3 days', 'notes': 'Unopened'}}),
        'POST /returns/terms/accept': (200, {'data': {'returns_terms_version': '1.2', 'returns_terms_accepted': true}}),
      });

      final policy = await repo.updateReturnPolicy(policy: '3d', notes: 'Unopened');
      await repo.acceptReturnsTerms('1.2');

      expect(policy.code, '3d');
      expect(adapter.requests[0].data, {'return_policy': '3d', 'return_notes': 'Unopened'});
      expect(adapter.requests[1].data, {'version': '1.2'});
    });
  });

  test('Bolt checkout opts in with fulfilment and the driver phone', () async {
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

  test('an ordinary checkout sends neither new field, and an order without fulfilment parses', () async {
    final adapter = _Scripted({
      'POST /orders': (201, {
        'data': {
          'id': 1, 'code': 'SK1', 'status': 'pending', 'subtotal': 20000, 'delivery_fee': 3000, 'total': 23000,
          'delivery_method': 'delivery', 'payment_method': 'cash_on_delivery', 'timeline': <Object>[],
        },
      }),
    });
    final dio = Dio(BaseOptions(baseUrl: 'https://x/api'))..httpClientAdapter = adapter;

    final order = await OrderRepository(api: OrderApi(dio)).checkout(
      items: [(productId: 9, qty: 1)],
      deliveryMethod: 'delivery',
      address: 'Somewhere',
      paymentMethod: 'cash_on_delivery',
    );

    final body = adapter.requests.single.data as Map<String, dynamic>;
    expect(body.containsKey('fulfilment'), isFalse);
    expect(body.containsKey('delivery_phone'), isFalse);
    expect(order.fulfilment, isNull);
  });
}
