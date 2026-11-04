import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:sokoni/features/delivery/presentation/rate_driver_screen.dart';

import '../../fixtures/delivery_fixtures.dart';
import 'delivery_test_support.dart';

void main() {
  testWidgets('the buyer rates the rider after delivery', (tester) async {
    final repo = FakeDeliveryRepository(view: buyerView(status: 'delivered'));
    await tester.pumpWidget(
      ProviderScope(
        overrides: deliveryOverrides(repo: repo),
        child: localizedApp(const RateDriverScreen(orderId: 345)),
      ),
    );
    await tester.pump();
    await tester.pump();

    expect(find.text('How was your delivery with Asha?'), findsOneWidget);

    await tester.tap(find.byKey(const Key('rate-star-4')));
    await tester.enterText(find.byType(TextField), 'Polite and fast');
    await tester.tap(find.text('Send rating'));
    await tester.pump();
    await tester.pump();

    final rate = repo.calls.singleWhere((c) => c['call'] == 'rate');
    expect(rate['rating'], 4);
    expect(rate['comment'], 'Polite and fast');

    await disposeTree(tester);
  });
}
