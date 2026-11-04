import 'dart:convert';
import 'dart:typed_data';

import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:sokoni/data/api/delivery_api.dart';
import 'package:sokoni/data/api/order_api.dart';
import 'package:sokoni/data/models/app_notification.dart';
import 'package:sokoni/data/models/order.dart';
import 'package:sokoni/data/repositories/delivery_repository.dart';
import 'package:sokoni/data/repositories/order_repository.dart';

import '../../fixtures/order_fixtures.dart';

/// Replays raw response bodies byte-for-byte, the way the server sent them.
class _Replay implements HttpClientAdapter {
  _Replay(this.routes);

  /// "METHOD /path" -> (status, raw body)
  final Map<String, (int, String)> routes;

  @override
  Future<ResponseBody> fetch(RequestOptions options, Stream<Uint8List>? requestStream, Future<void>? cancelFuture) async {
    final route = routes['${options.method} ${options.path}'];
    if (route == null) throw DioException.connectionError(requestOptions: options, reason: 'unscripted ${options.method} ${options.path}');
    return ResponseBody.fromString(route.$2, route.$1, headers: {
      Headers.contentTypeHeader: [Headers.jsonContentType],
    });
  }

  @override
  void close({bool force = false}) {}
}

Map<String, dynamic> _createdOrderJson() =>
    (jsonDecode(dispatchOrderCreatedResponse) as Map<String, dynamic>)['data'] as Map<String, dynamic>;

void main() {
  group('Regression 2026-11-04: Place order on a Sokoni-delivery order', () {
    test('the exact create response (status: null) parses', () {
      final order = Order.fromJson(_createdOrderJson());

      // Every order is pending the moment it's placed (the orders table default).
      expect(order.status, 'pending');
      expect(order.isCancellable, isTrue);
      expect(order.paymentStatus, isNull);
      expect(order.code, 'SKBJZLELSS');
      expect(order.isDispatch, isTrue);
      expect(order.deliveryMethod, 'delivery');
      expect(order.paymentMethod, 'cash_on_delivery');
      // Amounts exactly as the server recorded them: no fee until the shop accepts.
      expect(order.subtotal, 20000);
      expect(order.deliveryFee, 0);
      expect(order.total, 20000);
      expect(order.address, 'Mtaa wa Uhuru 12, Kariakoo');
      expect(order.deliveryPhone, '+255765000123');
      expect(order.notes, 'Blue gate');
      expect(order.conversationId, 1);
      expect(order.buyer!.name, 'Pedro Kiehn');
      expect(order.seller!.handle, 'ie9p1');
      expect(order.items.single.title, 'Architecto illum fugit');
      expect(order.timeline.single.status, 'pending');
      expect(order.timeline.single.at, DateTime.utc(2026, 11, 4, 9, 49, 36));
    });

    test('Place order end to end: checkout returns the order and the follow-up tracking call reads "no job yet"', () async {
      final dio = Dio(BaseOptions(baseUrl: 'https://staging-api.sokoni.co.tz/api'))
        ..httpClientAdapter = _Replay({
          'POST /orders': (201, dispatchOrderCreatedResponse),
          'GET /delivery/orders/1': (404, noDeliveryJobResponse),
        });

      final order = await OrderRepository(api: OrderApi(dio)).checkout(
        items: [(productId: 1, qty: 1)],
        deliveryMethod: 'delivery',
        fulfilment: 'dispatch',
        address: 'Mtaa wa Uhuru 12, Kariakoo',
        deliveryLat: -6.79,
        deliveryLng: 39.28,
        deliveryPhone: '+255765000123',
        notes: 'Blue gate',
        paymentMethod: 'cash_on_delivery',
      );
      expect(order.id, 1);
      expect(order.status, 'pending');

      expect(await DeliveryRepository(api: DeliveryApi(dio)).tracking(order.id), isNull);
    });
  });

  group('null-safe text, strict amounts', () {
    test('any text field the server leaves null falls back instead of throwing', () {
      final json = _createdOrderJson()
        ..['code'] = null
        ..['status'] = null
        ..['delivery_method'] = null
        ..['payment_method'] = null;
      (json['buyer'] as Map<String, dynamic>)
        ..['name'] = null
        ..['is_seller'] = null
        ..['terms_accepted'] = null;
      (json['seller'] as Map<String, dynamic>)
        ..['shop_name'] = null
        ..['handle'] = null;
      ((json['items'] as List).single as Map<String, dynamic>)['title'] = null;
      ((json['timeline'] as List).single as Map<String, dynamic>)
        ..['status'] = null
        ..['at'] = null;

      final order = Order.fromJson(json);

      expect(order.code, '');
      expect(order.status, 'pending');
      expect(order.deliveryMethod, '');
      expect(order.paymentMethod, '');
      expect(order.buyer!.name, '');
      expect(order.buyer!.isSeller, isFalse);
      expect(order.buyer!.termsAccepted, isFalse);
      expect(order.seller!.shopName, '');
      expect(order.seller!.handle, '');
      expect(order.items.single.title, '');
      expect(order.timeline.single.at, isNull);
      // The amounts are untouched: only text fell back.
      expect(order.total, 20000);
    });

    test('a missing amount is still an error, never a TSh 0 the server did not record', () {
      expect(() => Order.fromJson(_createdOrderJson()..['total'] = null), throwsA(isA<TypeError>()));
      expect(() => Order.fromJson(_createdOrderJson()..['delivery_fee'] = null), throwsA(isA<TypeError>()));
    });

    test('a notification with no title or body still lists', () {
      final n = AppNotification.fromJson({'id': 5, 'title': null, 'body': null, 'payload': {'order_id': 72}});
      expect(n.title, '');
      expect(n.body, '');
      expect(n.payload, {'order_id': 72});
    });
  });
}
