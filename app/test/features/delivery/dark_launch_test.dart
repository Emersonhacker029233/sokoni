import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:sokoni/data/models/delivery.dart';
import 'package:sokoni/features/delivery/presentation/request_return_screen.dart';
import 'package:sokoni/features/delivery/presentation/widgets/return_policy_card.dart';
import 'package:sokoni/features/delivery/providers/delivery_providers.dart';

import 'delivery_test_support.dart';

/// The app shows delivery and return UI only when `GET /features` says so
/// for this user (docs/delivery/BOLT.md, "Flags"). Off means the app looks
/// exactly like the build before delivery existed.
void main() {
  Future<DeliveryConfig?> availability(SokoniFeatures features, {String version = '1.1.0'}) async {
    final repo = FakeDeliveryRepository()..features0 = features;
    final container = ProviderContainer(overrides: [
      deliveryRepositoryProvider.overrideWithValue(repo),
      featuresProvider.overrideWith((ref) async => features),
      appVersionProvider.overrideWith((ref) async => version),
    ]);
    addTearDown(container.dispose);
    return container.read(deliveryAvailabilityProvider.future);
  }

  test('delivery off for this user: no Bolt checkout even though the server has a config', () async {
    expect(await availability(SokoniFeatures.off), isNull);
    expect(await availability(const SokoniFeatures(returnsEnabled: true)), isNull);
  });

  test('delivery on for this user: the Bolt checkout', () async {
    expect(await availability(const SokoniFeatures(deliveryEnabled: true)), isNotNull);
  });

  test('a build older than 1.1 never shows it', () async {
    expect(await availability(const SokoniFeatures(deliveryEnabled: true), version: '1.0.0'), isNull);
  });

  testWidgets('the return policy card draws nothing without a policy, a line with one, and Change for the owner', (tester) async {
    Future<void> pump(Map<String, dynamic>? json, {String? owner}) => tester.pumpWidget(
      ProviderScope(child: localizedApp(Scaffold(body: ReturnPolicyCard(policyJson: json, ownerHandle: owner)))),
    );

    await pump(null);
    expect(find.byKey(const Key('return-policy-label')), findsNothing);

    await pump({'code': '7d', 'hours': 168, 'label': 'Returns within 7 days', 'notes': 'Unused only'});
    expect(find.text('Returns within 7 days'), findsOneWidget);
    expect(find.text('Unused only'), findsOneWidget);
    expect(find.byKey(const Key('change-return-policy')), findsNothing);

    await pump({'code': 'none', 'hours': 0, 'label': 'No returns', 'notes': null}, owner: 'duka_la_juma');
    expect(find.text('No returns'), findsOneWidget);
    expect(find.byKey(const Key('change-return-policy')), findsOneWidget);
  });

  testWidgets('requesting a return needs a reason and at least one photo', (tester) async {
    final repo = FakeDeliveryRepository();
    tester.view.physicalSize = const Size(1080, 3200);
    tester.view.devicePixelRatio = 2.5;
    addTearDown(tester.view.reset);
    await tester.pumpWidget(ProviderScope(
      overrides: deliveryOverrides(repo: repo),
      child: localizedApp(const RequestReturnScreen(orderId: 345)),
    ));
    await tester.pump();

    expect(find.text('Why are you returning it?'), findsOneWidget);
    expect(find.text('You pay the Bolt pickup.'), findsOneWidget); // changed my mind
    expect(find.text('The shop pays the Bolt pickup.'), findsNWidgets(3));

    await tester.tap(find.byKey(const Key('return-reason-wrong_item')));
    await tester.pump();
    await tester.ensureVisible(find.byKey(const Key('send-return')));
    await tester.tap(find.byKey(const Key('send-return')));
    await tester.pump();

    expect(find.text('Choose a reason and add at least one photo.'), findsOneWidget);
    expect(repo.calls, isEmpty);
  });
}
