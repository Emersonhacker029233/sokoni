import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';

import 'package:sokoni/core/l10n/gen/app_localizations.dart';
import 'package:sokoni/core/router/routes.dart';
import 'package:sokoni/data/models/delivery.dart';
import 'package:sokoni/features/delivery/presentation/delivery_tracking_screen.dart';
import 'package:sokoni/features/delivery/providers/delivery_providers.dart';

import '../../fixtures/delivery_fixtures.dart';
import 'delivery_test_support.dart';

/// Opens the order screen stand-in, then the live tracking screen on top.
Future<GoRouter> _pumpTracking(WidgetTester tester, FakeDeliveryRepository repo, {Locale locale = const Locale('en')}) async {
  tester.view.physicalSize = const Size(1080, 2400);
  tester.view.devicePixelRatio = 2.5;
  addTearDown(tester.view.reset);
  final router = GoRouter(
    routes: [
      GoRoute(path: '/', builder: (context, state) => const Text('order screen')),
      GoRoute(
        path: SokoniRoutes.deliveryTrackingPattern,
        builder: (context, state) => DeliveryTrackingScreen(orderId: int.parse(state.pathParameters['id']!), showTiles: false),
      ),
    ],
  );
  await tester.pumpWidget(
    ProviderScope(
      overrides: deliveryOverrides(repo: repo),
      child: MaterialApp.router(
        locale: locale,
        localizationsDelegates: AppLocalizations.localizationsDelegates,
        supportedLocales: AppLocalizations.supportedLocales,
        routerConfig: router,
      ),
    ),
  );
  unawaited(router.push(SokoniRoutes.deliveryTracking(345)));
  for (var i = 0; i < 4; i++) {
    await tester.pump(const Duration(milliseconds: 200));
  }
  return router;
}

void main() {
  testWidgets('the map shows the shop, the drop-off and the driver, with the ETA, call, Track on Bolt and the timeline', (tester) async {
    final repo = FakeDeliveryRepository(view: buyerView(status: 'driver_assigned'))..locations = [liveLocationJson()];
    await _pumpTracking(tester, repo);

    expect(find.text('Live tracking'), findsOneWidget);
    expect(find.byKey(const Key('live-map')), findsOneWidget);
    expect(find.byKey(const Key('live-shop-marker')), findsOneWidget);
    expect(find.byKey(const Key('live-dropoff-marker')), findsOneWidget);
    expect(find.byKey(const Key('live-driver-marker')), findsOneWidget);
    expect(find.text('Driver reaches the shop in about 3 min'), findsOneWidget);
    expect(find.byKey(const Key('call-driver')), findsOneWidget);
    expect(find.byKey(const Key('bolt-tracking')), findsOneWidget);
    expect(find.text('Seyf Driver'), findsOneWidget);
    expect(find.text('Driver on the way to the shop'), findsWidgets); // the timeline
    expect(find.byKey(const Key('live-back-to-order')), findsNothing);

    await disposeTree(tester);
  });

  testWidgets('polls every 5 seconds while open and stops when the screen closes', (tester) async {
    final repo = FakeDeliveryRepository(view: buyerView(status: 'driver_assigned'))..locations = [liveLocationJson()];
    final router = await _pumpTracking(tester, repo);
    expect(repo.locationCalls, 1);

    await tester.pump(const Duration(seconds: 5));
    expect(repo.locationCalls, 2);
    await tester.pump(const Duration(seconds: 5));
    expect(repo.locationCalls, 3);

    router.pop();
    await tester.pump(const Duration(seconds: 1));
    await tester.pump(const Duration(seconds: 1));
    expect(find.text('order screen'), findsOneWidget);
    final calls = repo.locationCalls;
    await tester.pump(const Duration(seconds: 30));
    expect(repo.locationCalls, calls, reason: 'no polling once the screen is closed');

    await disposeTree(tester);
  });

  testWidgets('the driver moving on, a new status refreshes the order, and polling ends with the ride', (tester) async {
    final repo = FakeDeliveryRepository(view: buyerView(status: 'on_the_way'))
      ..locations = [
        liveLocationJson(status: 'on_the_way', eta: {'seconds': 400, 'to': 'customer', 'source': 'estimate'}),
        liveLocationJson(status: 'on_the_way', lat: -6.80, lng: 39.279, eta: {'seconds': 200, 'to': 'customer', 'source': 'estimate'}),
        liveLocationJson(status: 'delivered', lat: null, eta: null),
      ];
    await _pumpTracking(tester, repo);
    expect(find.text('About 7 min to you'), findsOneWidget);
    expect(find.text("Estimated from the driver's distance to you."), findsOneWidget);

    await tester.pump(const Duration(seconds: 5));
    await tester.pump(const Duration(milliseconds: 500)); // the marker glides to its new place
    expect(find.text('About 4 min to you'), findsOneWidget);
    expect(find.byKey(const Key('live-driver-marker')), findsOneWidget);

    repo.view = buyerView(status: 'delivered');
    final trackingReads = repo.calls.where((c) => c['call'] == 'tracking').length;
    await tester.pump(const Duration(seconds: 5));
    await tester.pump();
    expect(find.text('Delivered. Live tracking has stopped.'), findsOneWidget);
    expect(repo.calls.where((c) => c['call'] == 'tracking').length, greaterThan(trackingReads), reason: 'the order view is re-read when the status moves on');
    expect(find.byKey(const Key('live-back-to-order')), findsOneWidget);

    final calls = repo.locationCalls;
    await tester.pump(const Duration(seconds: 30));
    expect(repo.locationCalls, calls, reason: 'the ride is over: no more polls');

    await disposeTree(tester);
  });

  testWidgets('says when the driver is not located yet, or the position is the last known one', (tester) async {
    final recorded = DateTime.parse('2026-11-17T08:23:00Z');
    final repo = FakeDeliveryRepository(view: buyerView(status: 'driver_assigned'))
      ..locations = [
        liveLocationJson(lat: null, reason: 'driver_not_located'),
        liveLocationJson(stale: true, reason: 'rate_limited'),
      ];
    await _pumpTracking(tester, repo);
    expect(find.text('Locating the driver…'), findsOneWidget);
    expect(find.byKey(const Key('live-driver-marker')), findsNothing);

    await tester.pump(const Duration(seconds: 5));
    await tester.pump();
    expect(find.text("Driver's last known position, ${DateFormat('HH:mm').format(recorded.toLocal())}"), findsOneWidget);
    expect(find.byKey(const Key('live-driver-marker')), findsOneWidget);

    await disposeTree(tester);
  });

  testWidgets('a failed poll keeps the map, says it is reconnecting and tries again after 10 seconds', (tester) async {
    final repo = FakeDeliveryRepository(view: buyerView(status: 'driver_assigned'))..locations = [liveLocationJson()];
    await _pumpTracking(tester, repo);

    repo.locationError = Exception('offline');
    await tester.pump(const Duration(seconds: 5));
    await tester.pump();
    expect(find.text('Connection lost. Trying again…'), findsOneWidget);
    expect(find.byKey(const Key('live-driver-marker')), findsOneWidget);
    expect(repo.locationCalls, 2);

    repo.locationError = null;
    await tester.pump(const Duration(seconds: 5));
    expect(repo.locationCalls, 2, reason: 'backs off to 10 s after a failure');
    await tester.pump(const Duration(seconds: 5));
    await tester.pump();
    expect(repo.locationCalls, 3);
    expect(find.text('Connection lost. Trying again…'), findsNothing);

    await disposeTree(tester);
  });

  testWidgets('in Swahili', (tester) async {
    final repo = FakeDeliveryRepository(view: buyerView(status: 'driver_assigned'))..locations = [liveLocationJson()];
    await _pumpTracking(tester, repo, locale: const Locale('sw'));

    expect(find.text('Ufuatiliaji wa moja kwa moja'), findsOneWidget);
    expect(find.text('Dereva atafika dukani baada ya takriban dakika 3'), findsOneWidget);
    expect(find.text('Fuatilia kwenye Bolt'), findsOneWidget);

    await disposeTree(tester);
  });

  test('poll interval: the server\'s seconds kept between 5 and 10, 10 after a failure, none once the ride is over', () {
    LiveMapState state(Map<String, dynamic> json, {bool reconnecting = false}) =>
        LiveMapState(DriverLiveLocation.fromJson(json), reconnecting: reconnecting);

    expect(DriverLocationController.nextPoll(state(liveLocationJson())), const Duration(seconds: 5));
    expect(DriverLocationController.nextPoll(state({...liveLocationJson(), 'poll_seconds': 2})), const Duration(seconds: 5));
    expect(DriverLocationController.nextPoll(state({...liveLocationJson(), 'poll_seconds': 30})), const Duration(seconds: 10));
    expect(DriverLocationController.nextPoll(state(liveLocationJson(), reconnecting: true)), const Duration(seconds: 10));
    expect(DriverLocationController.nextPoll(state(liveLocationJson(status: 'delivered'))), isNull);
    expect(DriverLocationController.nextPoll(state(liveLocationJson(status: 'pending_driver', lat: null))), const Duration(seconds: 5));
  });

  test('the location answer is read defensively', () {
    final live = DriverLiveLocation.fromJson({
      'status': 'on_the_way',
      'active': true,
      'poll_seconds': '5',
      'location': {'lat': '-6.8', 'lng': 39.2, 'bearing': 270, 'recorded_at': '2026-11-17T08:23:00Z'},
      'eta': {'seconds': 61, 'to': 'customer', 'source': 'estimate'},
    });
    expect(live.position!.lat, -6.8);
    expect(live.position!.bearing, 270);
    expect(live.eta!.minutes, 2);
    expect(live.eta!.isEstimate, isTrue);
    expect(live.pickup, isNull);

    final empty = DriverLiveLocation.fromJson(const {});
    expect(empty.active, isFalse);
    expect(empty.position, isNull);
    expect(empty.eta, isNull);
  });
}
