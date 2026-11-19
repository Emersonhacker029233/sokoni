import 'dart:typed_data';

import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';

import 'package:sokoni/core/l10n/gen/app_localizations.dart';
import 'package:sokoni/core/router/routes.dart';
import 'package:sokoni/data/api/order_api.dart';
import 'package:sokoni/data/models/delivery.dart';
import 'package:sokoni/data/models/order.dart';
import 'package:sokoni/data/models/product.dart';
import 'package:sokoni/data/models/seller_summary.dart';
import 'package:sokoni/data/repositories/order_repository.dart';
import 'package:sokoni/features/delivery/presentation/delivery_location_picker_screen.dart';
import 'package:sokoni/features/delivery/providers/delivery_providers.dart';
import 'package:sokoni/features/orders/presentation/checkout_screen.dart';
import 'package:sokoni/features/orders/providers/order_providers.dart';

import '../../fixtures/delivery_fixtures.dart';
import '../../fixtures/order_fixtures.dart';
import 'delivery_test_support.dart';

class _SeededCart extends CartController {
  @override
  List<CartLine> build() => const [
    CartLine(
      product: Product(id: 9, title: 'Kitenge', price: 20000, seller: SellerSummary(id: 5, shopName: 'Duka la Juma', handle: 'dukalajuma')),
      qty: 1,
    ),
  ];
}

class _CapturingOrders extends OrderRepository {
  _CapturingOrders() : super(api: OrderApi(Dio()));

  Map<String, Object?>? sent;

  @override
  Future<Order> checkout({
    required List<({int productId, int qty})> items,
    required String deliveryMethod,
    String? address,
    double? deliveryLat,
    double? deliveryLng,
    String? notes,
    required String paymentMethod,
    String? fulfilment,
    String? deliveryPhone,
  }) async {
    sent = {
      'delivery_method': deliveryMethod,
      'address': address,
      'delivery_lat': deliveryLat,
      'delivery_lng': deliveryLng,
      'notes': notes,
      'payment_method': paymentMethod,
      'fulfilment': fulfilment,
      'delivery_phone': deliveryPhone,
    };
    return Order(
      id: 345, code: 'SKAB12CD34', status: 'pending', subtotal: 20000, deliveryFee: 0, total: 20000,
      deliveryMethod: deliveryMethod, fulfilment: fulfilment, paymentMethod: paymentMethod, conversationId: 77,
    );
  }
}

/// Stands in for the map: returns a fixed drop-off as if the buyer confirmed it.
class _InstantPicker extends StatefulWidget {
  const _InstantPicker();

  @override
  State<_InstantPicker> createState() => _InstantPickerState();
}

class _InstantPickerState extends State<_InstantPicker> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback(
      (_) => context.pop(const PickedDeliveryLocation(lat: -6.79, lng: 39.28, address: 'Mtaa wa Uhuru 12, Kariakoo')),
    );
  }

  @override
  Widget build(BuildContext context) => const Scaffold();
}

/// Answers POST /orders with raw bytes, as the server sent them.
class _ServerBytes implements HttpClientAdapter {
  _ServerBytes(this.status, this.body);

  final int status;
  final String body;
  int calls = 0;

  @override
  Future<ResponseBody> fetch(RequestOptions options, Stream<Uint8List>? requestStream, Future<void>? cancelFuture) async {
    calls++;
    return ResponseBody.fromString(body, status, headers: {
      Headers.contentTypeHeader: [Headers.jsonContentType],
    });
  }

  @override
  void close({bool force = false}) {}
}

Future<_CapturingOrders> _pump(WidgetTester tester, {required bool deliveryOn, Object? quoteError, OrderRepository? realOrders}) async {
  final orders = _CapturingOrders();
  final router = GoRouter(
    initialLocation: SokoniRoutes.checkout,
    routes: [
      GoRoute(path: SokoniRoutes.checkout, builder: (context, state) => const CheckoutScreen()),
      GoRoute(path: SokoniRoutes.deliveryLocation, builder: (context, state) => const _InstantPicker()),
      GoRoute(path: '/orders/:id', builder: (context, state) => Text('order ${state.pathParameters['id']}')),
      GoRoute(path: '/conversations/:id', builder: (context, state) => Text('chat ${state.pathParameters['id']}')),
    ],
  );
  tester.view.physicalSize = const Size(1080, 3200);
  tester.view.devicePixelRatio = 2.5;
  addTearDown(tester.view.reset);
  await tester.pumpWidget(
    ProviderScope(
      overrides: [
        ...deliveryOverrides(repo: FakeDeliveryRepository(), deliveryOn: deliveryOn),
        cartProvider.overrideWith(_SeededCart.new),
        orderRepositoryProvider.overrideWithValue(realOrders ?? orders),
        deliveryQuoteProvider.overrideWith((ref, request) async {
          if (quoteError != null) throw quoteError;
          return DeliveryQuote.fromJson(quoteJson(returnPolicy: {'code': '3d', 'hours': 72, 'label': 'Returns within 3 days', 'notes': 'Unused, in the original box.'}));
        }),
      ],
      child: MaterialApp.router(
        routerConfig: router,
        localizationsDelegates: AppLocalizations.localizationsDelegates,
        supportedLocales: AppLocalizations.supportedLocales,
        locale: const Locale('en'),
      ),
    ),
  );
  await tester.pumpAndSettle();
  return orders;
}

void main() {
  testWidgets('the ordinary path is unchanged when delivery is off: free-text address and flat TSh 3,000', (tester) async {
    final orders = await _pump(tester, deliveryOn: false);
    await tester.tap(find.text('Delivery'));
    await tester.pumpAndSettle();

    expect(find.widgetWithText(TextField, 'Delivery address'), findsOneWidget);
    expect(find.text('Use current location'), findsOneWidget);
    expect(find.text('Cash on delivery'), findsOneWidget);
    expect(find.text('TSh 3,000'), findsOneWidget);
    expect(find.text('TSh 23,000'), findsOneWidget);
    expect(find.text('Choose delivery location'), findsNothing);

    await tester.enterText(find.widgetWithText(TextField, 'Delivery address'), 'Mtaa wa Uhuru 12');
    await tester.tap(find.text('Place order'));
    await tester.pump();
    expect(orders.sent!['fulfilment'], isNull);
    expect(orders.sent!['delivery_phone'], isNull);
    await tester.pumpAndSettle(const Duration(seconds: 3));
  });

  testWidgets('Bolt delivery needs a map location before an order can be placed', (tester) async {
    final orders = await _pump(tester, deliveryOn: true);
    await tester.tap(find.text('Delivery'));
    await tester.pumpAndSettle();

    expect(find.text('Delivered by a Bolt driver, booked by Sokoni.'), findsOneWidget);
    expect(find.byKey(const Key('checkout-choose-location')), findsOneWidget);
    expect(find.text('TSh 3,000'), findsNothing);
    expect(find.text('Cash on delivery'), findsNothing);
    expect(find.text('You pay the fixed delivery fee in the app after the shop accepts.'), findsOneWidget);
    // The rider phone is prefilled from the account, shown in local format.
    expect(find.widgetWithText(TextField, '0711 000 111'), findsOneWidget);

    await tester.tap(find.text('Place order'));
    await tester.pump();
    expect(find.text('Choose the delivery location on the map.'), findsOneWidget);
    expect(orders.sent, isNull);
  });

  testWidgets('Bolt delivery shows the fixed fee, the return policy and places a dispatch order with pin, address and phone', (tester) async {
    final orders = await _pump(tester, deliveryOn: true);
    await tester.tap(find.text('Delivery'));
    await tester.pumpAndSettle();

    await tester.tap(find.byKey(const Key('checkout-choose-location')));
    await tester.pumpAndSettle();

    expect(find.text('Mtaa wa Uhuru 12, Kariakoo'), findsOneWidget);
    // Flat delivery fee: one fixed price, said plainly; Bolt's own prices never show.
    expect(find.text('Fixed delivery fee (4.2 km)'), findsOneWidget);
    expect(find.text('TSh 6,400'), findsOneWidget);
    expect(find.text('Total'), findsOneWidget);
    expect(find.text('TSh 26,400'), findsOneWidget);
    expect(find.text('Nearest Bolt driver about 4 min away'), findsOneWidget);
    expect(
      find.text('The delivery fee is a fixed price, not an estimate. It is confirmed when the shop accepts, '
          'you see it before you pay, and it never changes after that.'),
      findsOneWidget,
    );
    expect(find.text('You pay the fixed delivery fee in the app after the shop accepts.'), findsOneWidget);
    expect(find.textContaining('estimate TSh'), findsNothing);
    expect(find.textContaining('Bolt estimate'), findsNothing);
    expect(find.byKey(const Key('checkout-bolt-estimate')), findsNothing);
    expect(find.textContaining('upper amount'), findsNothing);
    expect(find.textContaining('can be lower'), findsNothing);
    expect(find.textContaining('Estimated'), findsNothing);
    // Returns, shown before the order is placed.
    expect(find.text('Returns: Returns within 3 days\nUnused, in the original box.'), findsOneWidget);

    await tester.enterText(find.byKey(const Key('checkout-rider-phone')), '0765 000 123');
    await tester.tap(find.text('Place order'));
    await tester.pump();

    expect(orders.sent, {
      'delivery_method': 'delivery',
      'address': 'Mtaa wa Uhuru 12, Kariakoo',
      'delivery_lat': -6.79,
      'delivery_lng': 39.28,
      'notes': null,
      'payment_method': 'cash_on_delivery',
      'fulfilment': 'dispatch',
      'delivery_phone': '+255765000123',
    });

    // After the success moment the order screen opens (not the chat), where Pay now lives.
    await tester.pumpAndSettle(const Duration(seconds: 3));
    expect(find.text('order 345'), findsOneWidget);
  });

  // Regression 2026-11-04 (staging order #72): the order was created but
  // the app showed "Unexpected error: type 'Null' is not a subtype of type
  // 'String' in type cast", left the cart full and invited a second order.
  testWidgets('Place order with the real server response opens the order, no error, cart emptied', (tester) async {
    final server = _ServerBytes(201, dispatchOrderCreatedResponse);
    await _pump(
      tester,
      deliveryOn: true,
      realOrders: OrderRepository(api: OrderApi(Dio(BaseOptions(baseUrl: 'https://staging-api.sokoni.co.tz/api'))..httpClientAdapter = server)),
    );
    final container = ProviderScope.containerOf(tester.element(find.byType(CheckoutScreen)));
    await tester.tap(find.text('Delivery'));
    await tester.pumpAndSettle();
    await tester.tap(find.byKey(const Key('checkout-choose-location')));
    await tester.pumpAndSettle();
    await tester.enterText(find.byKey(const Key('checkout-rider-phone')), '0765 000 123');

    await tester.tap(find.text('Place order'));
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 100));

    expect(server.calls, 1);
    expect(find.textContaining('Unexpected error'), findsNothing);
    expect(find.textContaining('SKBJZLELSS'), findsWidgets);
    expect(container.read(cartProvider), isEmpty);

    await tester.pumpAndSettle(const Duration(seconds: 3));
    expect(find.text('order 1'), findsOneWidget);
  });

  testWidgets('an invalid rider phone blocks the order', (tester) async {
    final orders = await _pump(tester, deliveryOn: true);
    await tester.tap(find.text('Delivery'));
    await tester.pumpAndSettle();
    await tester.tap(find.byKey(const Key('checkout-choose-location')));
    await tester.pumpAndSettle();

    await tester.enterText(find.byKey(const Key('checkout-rider-phone')), '12345');
    await tester.tap(find.text('Place order'));
    await tester.pump();

    expect(find.text('Enter a valid Tanzanian mobile number, e.g. 0754 123 456.'), findsOneWidget);
    expect(orders.sent, isNull);
  });

  testWidgets('too far for delivery: the server reason and a way back to pickup', (tester) async {
    await _pump(
      tester,
      deliveryOn: true,
      quoteError: const DeliveryActionError(code: 'too_far', message: 'Delivery is available up to 10 km from the shop. Choose pickup instead.', statusCode: 422),
    );
    await tester.tap(find.text('Delivery'));
    await tester.pumpAndSettle();
    await tester.tap(find.byKey(const Key('checkout-choose-location')));
    await tester.pumpAndSettle();

    expect(find.text('Delivery is available up to 10 km from the shop. Choose pickup instead.'), findsOneWidget);
    await tester.tap(find.text('Choose pickup'));
    await tester.pumpAndSettle();
    expect(find.byKey(const Key('checkout-choose-location')), findsNothing);
  });
  testWidgets('a shop outside the Bolt test: ordinary shop delivery instead, not just pickup', (tester) async {
    final orders = await _pump(
      tester,
      deliveryOn: true,
      quoteError: const DeliveryActionError(code: 'not_available', message: 'Delivery is not available from this shop yet. Choose pickup instead.', statusCode: 422),
    );
    await tester.tap(find.text('Delivery'));
    await tester.pumpAndSettle();
    await tester.tap(find.byKey(const Key('checkout-choose-location')));
    await tester.pumpAndSettle();

    await tester.tap(find.text('Use shop delivery'));
    await tester.pumpAndSettle();

    expect(find.widgetWithText(TextField, 'Delivery address'), findsOneWidget);
    expect(find.text('TSh 3,000'), findsOneWidget);
    await tester.enterText(find.widgetWithText(TextField, 'Delivery address'), 'Mtaa wa Uhuru 12');
    await tester.tap(find.text('Place order'));
    await tester.pump();
    expect(orders.sent!['fulfilment'], isNull);
    await tester.pumpAndSettle(const Duration(seconds: 3));
  });
}
