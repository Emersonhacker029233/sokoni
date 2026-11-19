import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';

import 'package:sokoni/core/l10n/gen/app_localizations.dart';
import 'package:sokoni/core/router/routes.dart';

import 'package:sokoni/data/models/delivery.dart';
import 'package:sokoni/data/models/order.dart';
import 'package:sokoni/data/models/user.dart';
import 'package:sokoni/features/moderation/presentation/moderation_menu.dart';
import 'package:sokoni/features/orders/presentation/order_detail_screen.dart';
import 'package:sokoni/features/orders/providers/order_providers.dart';

import '../../fixtures/delivery_fixtures.dart';
import 'delivery_test_support.dart';

Order _boltOrder({String status = 'accepted'}) => Order.fromJson({
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
    {'status': 'pending', 'at': '2026-11-17T07:59:00Z'},
  ],
});

Future<FakeDeliveryRepository> _pump(
  WidgetTester tester, {
  required Map<String, dynamic>? view,
  Map<String, dynamic>? afterPay,
  SokoniUser user = testBuyer,
  String orderStatus = 'accepted',
  Locale locale = const Locale('en'),
  void Function(FakeDeliveryRepository repo)? setUp,
}) async {
  final repo = FakeDeliveryRepository(view: view, afterPay: afterPay);
  setUp?.call(repo);
  tester.view.physicalSize = const Size(1080, 2400);
  tester.view.devicePixelRatio = 2.5;
  addTearDown(tester.view.reset);
  await tester.pumpWidget(
    ProviderScope(
      overrides: [
        ...deliveryOverrides(repo: repo, user: user),
        orderDetailProvider.overrideWith((ref, id) async => _boltOrder(status: orderStatus)),
      ],
      child: MaterialApp.router(
        locale: locale,
        localizationsDelegates: AppLocalizations.localizationsDelegates,
        supportedLocales: AppLocalizations.supportedLocales,
        routerConfig: GoRouter(routes: [
          GoRoute(path: '/', builder: (context, state) => const OrderDetailScreen(orderId: 345)),
          GoRoute(path: SokoniRoutes.requestReturnPattern, builder: (context, state) => Text('request return ${state.pathParameters['id']}')),
          GoRoute(path: SokoniRoutes.deliveryTrackingPattern, builder: (context, state) => Text('live tracking ${state.pathParameters['id']}')),
        ]),
      ),
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
      afterPay: buyerView(status: 'pending_payment', attempt: {'id': 1, 'status': 'pending', 'amount': 6400, 'channel': 'mpesa', 'msisdn': '+25571••••111'}),
    );

    expect(find.text('Pay for delivery'), findsOneWidget);
    expect(find.text('TSh 26,400'), findsWidgets); // total, from the server
    expect(find.text('Pay now · TSh 6,400'), findsOneWidget);
    // Flat delivery fee: the amount is fixed, and nothing says it could be lower.
    expect(find.text('Fixed delivery fee'), findsWidgets);
    expect(find.text("Pay now covers the fixed delivery fee. It won't change after you pay."), findsOneWidget);
    expect(find.textContaining('can be lower'), findsNothing);
    expect(find.textContaining('estimate'), findsNothing);
    expect(find.textContaining('Pay within'), findsOneWidget);
    expect(find.byKey(const Key('bolt-tracking')), findsNothing);

    await tester.ensureVisible(find.byKey(const Key('pay-now')));
    await tester.tap(find.byKey(const Key('pay-now')));
    await tester.pump();
    await tester.pump();

    final pay = repo.calls.singleWhere((c) => c['call'] == 'pay');
    expect(pay['channel'], 'mpesa');
    expect(pay['msisdn'], '+255711000111'); // the account phone, prefilled
    expect((pay['key']! as String).length, 24);
    // Still unpaid: the screen waits for the server, it never decides "paid".
    expect(find.textContaining('Check your phone and approve TSh 6,400'), findsOneWidget);
    expect(find.byKey(const Key('pay-now')), findsNothing);

    await disposeTree(tester);
  });

  testWidgets('paid: waiting for the shop, then the Bolt driver card with the tracking link', (tester) async {
    await _pump(tester, view: buyerView(status: 'awaiting_ready'));
    expect(find.text('Paid. A Bolt driver is booked as soon as the shop has packed your order.'), findsOneWidget);
    expect(find.text('Delivery fee paid: TSh 6,400'), findsOneWidget);
    await disposeTree(tester);

    await _pump(tester, view: buyerView(status: 'driver_assigned'));
    expect(find.text('Your Bolt driver'), findsOneWidget);
    expect(find.text('Seyf Driver'), findsOneWidget);
    expect(find.byKey(const Key('bolt-plate')), findsOneWidget);
    expect(find.text('Toyota IST · T123ABC'), findsOneWidget);
    expect(find.byKey(const Key('call-driver')), findsOneWidget);
    expect(find.byKey(const Key('bolt-tracking')), findsOneWidget);
    expect(find.text('Driver on the way to the shop'), findsWidgets);
    await disposeTree(tester);

    await _pump(tester, view: buyerView(status: 'driver_assigned'), locale: const Locale('sw'));
    expect(find.text('Dereva wako wa Bolt'), findsOneWidget);
    expect(find.text('Fuatilia kwenye Bolt'), findsOneWidget);
    await disposeTree(tester);
  });

  testWidgets('no Bolt driver: retry, switch to pickup and cancel; area not serviced: no retry', (tester) async {
    final repo = await _pump(tester, view: buyerView(status: 'no_driver_found', failureReason: 'no_driver_found'));
    expect(find.text('No Bolt driver available'), findsWidgets);
    expect(find.text('Try again'), findsOneWidget);
    expect(find.text('Switch to pickup'), findsOneWidget);
    expect(find.text('Cancel delivery'), findsOneWidget);
    await tester.tap(find.text('Try again'));
    await tester.pump();
    expect(repo.calls.where((c) => c['call'] == 'retry'), hasLength(1));
    await disposeTree(tester);

    await _pump(tester, view: buyerView(status: 'no_driver_found', failureReason: 'area_not_serviced'));
    expect(find.text('Bolt does not deliver on this route yet. Switch to pickup or cancel.'), findsOneWidget);
    expect(find.text('Try again'), findsNothing);
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
      view: buyerView(status: 'cancelled')..addAll({'cancelled_by': 'buyer', 'refund': {'amount': 6400, 'status': 'pending'}}),
      orderStatus: 'cancelled',
    );
    expect(find.textContaining('Refund of TSh 6,400 pending'), findsOneWidget);
    expect(find.text('You cancelled this delivery.'), findsOneWidget);
    await disposeTree(tester);
  });

  testWidgets('Bolt dropping the driver is explained', (tester) async {
    await _pump(
      tester,
      view: buyerView(status: 'pending_driver', timeline: [
        {'status': 'pending_driver', 'at': '2026-11-17T08:01:00Z', 'by': 'system'},
        {'status': 'driver_assigned', 'at': '2026-11-17T08:02:00Z', 'by': 'provider'},
        {'status': 'pending_driver', 'at': '2026-11-17T08:05:00Z', 'by': 'provider'},
      ]),
    );
    expect(find.text('Your Bolt driver dropped the trip. Bolt is finding another one.'), findsOneWidget);
    await disposeTree(tester);
  });

  testWidgets('seller mode: paid means mark ready; then the Bolt driver, never the customer phone', (tester) async {
    await _pump(tester, view: sellerView(status: 'awaiting_ready'), user: testSeller);
    expect(find.text('Bolt delivery'), findsOneWidget);
    expect(find.textContaining('tap "Mark as Ready"'), findsOneWidget);
    await disposeTree(tester);

    await _pump(tester, view: sellerView(status: 'arrived_at_shop'), user: testSeller);
    expect(find.text('The Bolt driver is at your shop. Hand over the order.'), findsOneWidget);
    expect(find.text('Seyf Driver'), findsOneWidget);
    expect(find.byKey(const Key('call-driver')), findsOneWidget); // before pickup only, per the server
    expect(find.textContaining('0765'), findsNothing);
    expect(find.textContaining('Pay now'), findsNothing);
    await disposeTree(tester);
  });

  testWidgets('seller mode before acceptance: Accept order, and no manual "completed" later', (tester) async {
    await _pump(tester, view: null, user: testSeller, orderStatus: 'pending');
    expect(find.text('Accept order'), findsOneWidget);
    expect(find.textContaining('a Bolt driver, booked by Sokoni, collects it'), findsOneWidget);
    await disposeTree(tester);

    await _pump(tester, view: sellerView(status: 'on_the_way'), user: testSeller, orderStatus: 'ready');
    expect(find.byKey(const Key('order-advance')), findsNothing);
    expect(find.text('The Bolt driver is delivering the order.'), findsOneWidget);
    await disposeTree(tester);
  });

  group('returns', () {
    testWidgets('delivered: the policy and Request a return, agreeing to the Return Policy first', (tester) async {
      final repo = await _pump(
        tester,
        view: buyerView(status: 'delivered', returnBlock: returnBlockJson()),
        orderStatus: 'completed',
        setUp: (repo) => repo.features0 = const SokoniFeatures(deliveryEnabled: true, returnsEnabled: true, returnsTermsVersion: '1.2'),
      );

      expect(find.byKey(const Key('order-return-policy')), findsOneWidget);
      expect(find.text('Returns within 3 days'), findsOneWidget);
      await tester.ensureVisible(find.byKey(const Key('request-return')));
      await tester.tap(find.byKey(const Key('request-return')));
      await tester.pumpAndSettle(const Duration(milliseconds: 100), EnginePhase.sendSemanticsUpdate, const Duration(seconds: 2));

      expect(find.text('Return Policy (Terms 1.2)'), findsOneWidget);
      await tester.tap(find.byKey(const Key('agree-returns-terms')));
      await tester.pump();
      expect(repo.calls.where((c) => c['call'] == 'acceptReturnsTerms' && c['version'] == '1.2'), hasLength(1));
      await tester.pumpAndSettle();
      expect(find.text('request return 345'), findsOneWidget);
      await disposeTree(tester);
    });

    testWidgets('no return block outside the returns launch: no return UI at all', (tester) async {
      await _pump(tester, view: buyerView(status: 'delivered'), orderStatus: 'completed');
      expect(find.byKey(const Key('request-return')), findsNothing);
      expect(find.text('Return'), findsNothing);
      await disposeTree(tester);
    });

    testWidgets('the shop sees the request and rejects it with a reason; it can report it', (tester) async {
      final repo = await _pump(
        tester,
        user: testSeller,
        orderStatus: 'completed',
        view: sellerView(status: 'delivered', returnBlock: returnBlockJson(request: returnRequestJson(actions: {'can_accept': true, 'can_reject': true, 'can_report': true}))),
      );

      expect(find.text('Wrong item'), findsOneWidget);
      expect(find.text('It is the blue one, I ordered black.'), findsOneWidget);
      expect(find.byKey(const Key('accept-return')), findsOneWidget);
      expect(find.byKey(const Key('report-return')), findsOneWidget);
      // Report the request and block the buyer, from the request itself.
      final menu = tester.widget<ModerationMenuButton>(
        find.descendant(of: find.byKey(const Key('report-return')), matching: find.byType(ModerationMenuButton)),
      );
      expect(menu.target.reportType, 'return_request');
      expect(menu.target.reportLabel, 'Report this return request');
      expect(menu.target.authorUserId, 1);
      expect(menu.target.authorName, 'Neema Buyer');

      await tester.ensureVisible(find.byKey(const Key('reject-return')));
      await tester.tap(find.byKey(const Key('reject-return')));
      await tester.pumpAndSettle();
      await tester.enterText(find.byType(TextField).last, 'The photo shows it as described');
      await tester.pump();
      await tester.tap(find.widgetWithText(FilledButton, 'Reject return'));
      await tester.pumpAndSettle();

      final reject = repo.calls.singleWhere((c) => c['call'] == 'return:reject');
      expect(reject['body'], {'reason': 'The photo shows it as described'});
      await disposeTree(tester);
    });

    testWidgets('the buyer books the Bolt pickup once the shop accepted (the shop pays)', (tester) async {
      final repo = await _pump(
        tester,
        orderStatus: 'completed',
        view: buyerView(status: 'delivered', returnBlock: returnBlockJson(canRequest: false, request: returnRequestJson(status: 'accepted', actions: {'can_book_ride': true, 'can_withdraw': true}))),
      );

      expect(find.text('Bolt pickup TSh 6,400, paid by the shop.'), findsOneWidget);
      await tester.ensureVisible(find.byKey(const Key('book-return-ride')));
      await tester.tap(find.byKey(const Key('book-return-ride')));
      await tester.pumpAndSettle();
      await tester.tap(find.widgetWithText(FilledButton, 'Yes, continue'));
      await tester.pumpAndSettle();

      expect(repo.calls.where((c) => c['call'] == 'return:book'), hasLength(1));
      await disposeTree(tester);
    });

    testWidgets('back at the shop: the shop confirms "item received, refund done"', (tester) async {
      final repo = await _pump(
        tester,
        user: testSeller,
        orderStatus: 'completed',
        view: sellerView(status: 'delivered', returnBlock: returnBlockJson(canRequest: false, request: returnRequestJson(status: 'received', actions: {'can_confirm_refund': true}))),
      );

      expect(find.text('Back at the shop'), findsOneWidget);
      await tester.ensureVisible(find.byKey(const Key('confirm-refund')));
      await tester.tap(find.byKey(const Key('confirm-refund')));
      await tester.pumpAndSettle();
      await tester.tap(find.widgetWithText(FilledButton, 'Item received, refund done').last);
      await tester.pumpAndSettle();

      expect(repo.calls.where((c) => c['call'] == 'return:refunded'), hasLength(1));
      await disposeTree(tester);
    });

    testWidgets('a pickup fee for a ride that never carried the item is shown as being refunded', (tester) async {
      await _pump(
        tester,
        orderStatus: 'completed',
        view: buyerView(
          status: 'delivered',
          returnBlock: returnBlockJson(canRequest: false, request: returnRequestJson(status: 'closed', payer: 'buyer', feeStatus: 'refund_pending')),
        ),
      );
      expect(find.text('Sokoni is refunding your TSh 6,400 pickup fee.'), findsOneWidget);
      expect(find.textContaining('You pay the Bolt pickup'), findsNothing);
      await disposeTree(tester);
    });

    testWidgets('paying for the ride back offers only the channels the server takes', (tester) async {
      await _pump(
        tester,
        orderStatus: 'completed',
        view: buyerView(
          status: 'delivered',
          returnBlock: returnBlockJson(
            canRequest: false,
            request: returnRequestJson(status: 'accepted', payer: 'buyer', channels: ['airtel'], actions: {'can_pay': true}),
          ),
        ),
      );
      expect(find.widgetWithText(ChoiceChip, 'Airtel Money'), findsOneWidget);
      expect(find.widgetWithText(ChoiceChip, 'M-Pesa'), findsNothing);
      expect(find.byKey(const Key('return-pay-unavailable')), findsNothing);
      await disposeTree(tester);
    });

    testWidgets('owing the ride fee without online payment: told to contact Sokoni, never a dead end', (tester) async {
      await _pump(
        tester,
        orderStatus: 'completed',
        view: buyerView(
          status: 'delivered',
          returnBlock: returnBlockJson(canRequest: false, request: returnRequestJson(status: 'accepted', payer: 'buyer', actions: {'can_withdraw': true})),
        ),
      );
      expect(find.text("Online payment isn't available right now. Contact Sokoni support."), findsOneWidget);
      expect(find.byKey(const Key('pay-return-ride')), findsNothing);
      await disposeTree(tester);
    });
  });

  group('live tracking and price', () {
    testWidgets('"Track live on the map" from driver assigned until the ride ends, and it opens the map', (tester) async {
      await _pump(tester, view: buyerView(status: 'awaiting_ready'));
      expect(find.byKey(const Key('open-live-tracking')), findsNothing);
      await disposeTree(tester);

      await _pump(tester, view: buyerView(status: 'delivered'), orderStatus: 'completed');
      expect(find.byKey(const Key('open-live-tracking')), findsNothing);
      await disposeTree(tester);

      await _pump(tester, view: buyerView(status: 'on_the_way'));
      expect(find.text('Track live on the map'), findsOneWidget);
      await tester.ensureVisible(find.byKey(const Key('open-live-tracking')));
      await tester.tap(find.byKey(const Key('open-live-tracking')));
      await tester.pumpAndSettle();
      expect(find.text('live tracking 345'), findsOneWidget);
      await disposeTree(tester);
    });

    // Flat delivery fee: one fixed fee before and after paying; never Bolt's
    // estimate range, receipt or a "can be lower" note.
    testWidgets('before paying: the fixed fee and the return policy, no Bolt estimate', (tester) async {
      await _pump(tester, view: buyerView(status: 'pending_payment', returnBlock: returnBlockJson(canRequest: false)));

      expect(find.byKey(const Key('pay-fixed-fee')), findsOneWidget);
      expect(find.text("Pay now covers the fixed delivery fee. It won't change after you pay."), findsOneWidget);
      expect(find.text('Returns: Returns within 3 days\nUnused, in the original box.'), findsOneWidget);
      final fee = find.byKey(const Key('order-delivery-fee'));
      expect(find.descendant(of: fee, matching: find.text('Fixed delivery fee')), findsOneWidget);
      expect(find.descendant(of: fee, matching: find.text('TSh 6,400')), findsOneWidget);
      expect(find.byKey(const Key('pay-bolt-estimate')), findsNothing);
      expect(find.textContaining('Bolt estimate'), findsNothing);
      expect(find.textContaining('can be lower'), findsNothing);
      expect(find.textContaining('TSh 5,200'), findsNothing);
      await disposeTree(tester);
    });

    testWidgets('after delivery: the fixed fee paid, never Bolt\'s receipt, even if a server sent one', (tester) async {
      await _pump(tester, view: buyerView(status: 'delivered')..['final_cost'] = 5900, orderStatus: 'completed');
      final fee = find.byKey(const Key('order-delivery-fee'));
      expect(find.descendant(of: fee, matching: find.text('Fixed delivery fee, paid')), findsOneWidget);
      expect(find.descendant(of: fee, matching: find.text('TSh 6,400')), findsOneWidget);
      expect(find.byKey(const Key('order-bolt-receipt')), findsNothing);
      expect(find.textContaining("Bolt's receipt"), findsNothing);
      expect(find.text('TSh 5,900'), findsNothing);
      expect(find.textContaining('can be lower'), findsNothing);
      await disposeTree(tester);

      await _pump(tester, view: buyerView(status: 'delivered'), orderStatus: 'completed', locale: const Locale('sw'));
      expect(find.text('Ada ya usafirishaji isiyobadilika, imelipwa'), findsOneWidget);
      expect(find.textContaining('Risiti ya Bolt'), findsNothing);
      expect(find.textContaining('Inasubiri Bolt'), findsNothing);
      await disposeTree(tester);
    });

    testWidgets('the shop never sees the fee or Bolt\'s receipt', (tester) async {
      await _pump(tester, view: sellerView(status: 'delivered'), user: testSeller, orderStatus: 'completed');
      expect(find.byKey(const Key('order-bolt-receipt')), findsNothing);
      expect(find.byKey(const Key('order-delivery-fee')), findsNothing);
      expect(find.byKey(const Key('open-live-tracking')), findsNothing);
      await disposeTree(tester);
    });
  });
}
