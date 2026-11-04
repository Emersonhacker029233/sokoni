import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:sokoni/data/models/order.dart';
import 'package:sokoni/data/models/user.dart';
import 'package:sokoni/features/orders/presentation/order_detail_screen.dart';
import 'package:sokoni/features/orders/providers/order_providers.dart';

import '../../fixtures/delivery_fixtures.dart';
import 'delivery_test_support.dart';

Order _dispatchOrder({String status = 'accepted'}) => Order.fromJson({
  'id': 345,
  'code': 'SKAB12CD34',
  'status': status,
  'subtotal': 20000,
  'delivery_fee': 0,
  'total': 20000,
  'delivery_method': 'delivery',
  'fulfilment': 'dispatch',
  'address': 'Mtaa wa Uhuru 12',
  'payment_method': 'cash_on_delivery',
  'buyer': {'id': 1, 'name': 'Neema Buyer', 'phone': null, 'is_seller': false, 'terms_accepted': true},
  'items': [
    {'id': 1, 'product_id': 9, 'title': 'Kitenge', 'price': 20000, 'qty': 1, 'line_total': 20000},
  ],
  'timeline': [
    {'status': 'pending', 'at': '2026-11-04T07:59:00Z'},
  ],
});

Future<FakeDeliveryRepository> _pump(
  WidgetTester tester, {
  required Map<String, dynamic>? view,
  Map<String, dynamic>? afterPay,
  SokoniUser user = testBuyer,
  String orderStatus = 'accepted',
  Locale locale = const Locale('en'),
}) async {
  final repo = FakeDeliveryRepository(view: view, afterPay: afterPay);
  tester.view.physicalSize = const Size(1080, 2400);
  tester.view.devicePixelRatio = 2.5;
  addTearDown(tester.view.reset);
  await tester.pumpWidget(
    ProviderScope(
      overrides: [
        ...deliveryOverrides(repo: repo, user: user),
        orderDetailProvider.overrideWith((ref, id) async => _dispatchOrder(status: orderStatus)),
      ],
      child: localizedApp(const OrderDetailScreen(orderId: 345), locale: locale),
    ),
  );
  await tester.pump();
  await tester.pump();
  await tester.pump(const Duration(milliseconds: 100));
  return repo;
}

void main() {
  testWidgets('after the shop accepts: the server breakdown and Pay now with the amount due', (tester) async {
    final repo = await _pump(
      tester,
      view: buyerView(status: 'pending_payment'),
      afterPay: buyerView(status: 'pending_payment', attempt: {'id': 1, 'status': 'pending', 'amount': 4500, 'channel': 'mpesa', 'msisdn': '+25571••••111'}),
    );

    expect(find.text('Pay for delivery'), findsOneWidget);
    expect(find.text('TSh 24,500'), findsWidgets); // total, from the server
    expect(find.text('Pay now · TSh 4,500'), findsOneWidget);
    expect(find.textContaining('Pay within'), findsOneWidget);
    expect(find.textContaining('Test mode'), findsOneWidget);
    expect(find.byKey(const Key('delivery-pin')), findsNothing);

    await tester.ensureVisible(find.byKey(const Key('pay-now')));
    await tester.tap(find.byKey(const Key('pay-now')));
    await tester.pump();
    await tester.pump();

    final pay = repo.calls.singleWhere((c) => c['call'] == 'pay');
    expect(pay['channel'], 'mpesa');
    expect(pay['msisdn'], '+255711000111'); // the account phone, prefilled
    expect((pay['key']! as String).length, 24);
    // Still unpaid: the screen waits for the server, it never decides "paid".
    expect(find.textContaining('Check your phone and approve TSh 4,500'), findsOneWidget);
    expect(find.byKey(const Key('pay-now')), findsNothing);

    await disposeTree(tester);
  });

  testWidgets('finding a driver: the PIN and the warning, in English and Swahili', (tester) async {
    await _pump(tester, view: buyerView(status: 'pending_driver'));
    expect(find.text('4821'), findsOneWidget);
    expect(find.text('Give this to the rider only when you receive your order.'), findsOneWidget);
    expect(find.text('Finding a driver'), findsOneWidget);
    expect(find.text('Delivery fee paid: TSh 4,500'), findsOneWidget);
    await disposeTree(tester);

    await _pump(tester, view: buyerView(status: 'pending_driver'), locale: const Locale('sw'));
    expect(find.text('Mpe dereva PIN hii pale tu utakapopokea oda yako.'), findsOneWidget);
    expect(find.text('Tunatafuta dereva'), findsOneWidget);
    await disposeTree(tester);
  });

  testWidgets('no driver found: retry, switch to pickup and cancel are offered', (tester) async {
    final repo = await _pump(tester, view: buyerView(status: 'no_driver_found'));

    expect(find.text('No driver available'), findsWidgets);
    expect(find.text('Try again'), findsOneWidget);
    expect(find.text('Switch to pickup'), findsOneWidget);
    expect(find.text('Cancel delivery'), findsOneWidget);

    await tester.tap(find.text('Try again'));
    await tester.pump();
    expect(repo.calls.where((c) => c['call'] == 'retry'), hasLength(1));
    await disposeTree(tester);
  });

  testWidgets('unpaid for 20 minutes: a clear cancellation message', (tester) async {
    await _pump(
      tester,
      view: buyerView(status: 'cancelled')..addAll({'cancelled_by': 'system', 'cancel_reason': 'payment_timeout'}),
      orderStatus: 'cancelled',
    );
    expect(find.text("The delivery fee wasn't paid within 20 minutes, so the order was cancelled."), findsOneWidget);
    await disposeTree(tester);
  });

  testWidgets('a paid delivery cancelled later shows the recorded refund as pending', (tester) async {
    await _pump(
      tester,
      view: buyerView(status: 'cancelled')..addAll({'cancelled_by': 'buyer', 'refund': {'amount': 4500, 'status': 'pending'}}),
      orderStatus: 'cancelled',
    );
    expect(find.textContaining('Refund of TSh 4,500 pending'), findsOneWidget);
    expect(find.text('You cancelled this delivery.'), findsOneWidget);
    await disposeTree(tester);
  });

  testWidgets('a rider who cancelled is explained, and delivered offers the rating', (tester) async {
    await _pump(
      tester,
      view: buyerView(status: 'pending_driver', timeline: [
        {'status': 'pending_driver', 'at': '2026-11-04T08:01:00Z', 'by': 'system'},
        {'status': 'driver_assigned', 'at': '2026-11-04T08:02:00Z', 'by': 'driver'},
        {'status': 'pending_driver', 'at': '2026-11-04T08:05:00Z', 'by': 'driver'},
      ]),
    );
    expect(find.text("Your rider had to cancel. We're finding you another one."), findsOneWidget);
    await disposeTree(tester);

    await _pump(tester, view: buyerView(status: 'delivered'), orderStatus: 'completed');
    expect(find.byKey(const Key('rate-driver')), findsOneWidget);
    expect(find.byKey(const Key('delivery-pin')), findsNothing);
    expect(find.text('Asha'), findsOneWidget);
    await disposeTree(tester);
  });

  testWidgets('seller mode: courier status, pickup code and rider, never the customer phone or PIN', (tester) async {
    await _pump(tester, view: sellerView(status: 'arrived_at_shop'), user: testSeller);

    expect(find.text('Sokoni delivery'), findsOneWidget);
    expect(find.text('The rider is at your shop.'), findsOneWidget);
    expect(find.byKey(const Key('pickup-code')), findsOneWidget);
    expect(find.text('CD34'), findsOneWidget);
    expect(find.text('Asha'), findsOneWidget);
    expect(find.byKey(const Key('call-driver')), findsOneWidget); // before pickup only, per the server
    expect(find.text('4821'), findsNothing);
    expect(find.textContaining('0765'), findsNothing);
    expect(find.textContaining('Pay now'), findsNothing);
    await disposeTree(tester);
  });

  testWidgets('seller mode before acceptance: Accept order, and no manual "completed" later', (tester) async {
    await _pump(tester, view: null, user: testSeller, orderStatus: 'pending');
    expect(find.text('Accept order'), findsOneWidget);
    expect(find.textContaining('a Sokoni rider collects it'), findsOneWidget);
    await disposeTree(tester);

    await _pump(tester, view: sellerView(status: 'on_the_way'), user: testSeller, orderStatus: 'ready');
    expect(find.byKey(const Key('order-advance')), findsNothing);
    expect(find.text('The rider is delivering the order.'), findsOneWidget);
    await disposeTree(tester);
  });
}
