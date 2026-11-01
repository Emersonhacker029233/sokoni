import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:sokoni_driver/core/l10n/gen/app_localizations.dart';
import 'package:sokoni_driver/core/theme/app_theme.dart';
import 'package:sokoni_driver/data/models/delivery_offer.dart';
import 'package:sokoni_driver/features/home/offer_card.dart';
import 'package:sokoni_driver/shared/widgets/common.dart';
import 'package:sokoni_driver/shared/widgets/success_check.dart';

import 'fixtures.dart';

Widget _app(Widget child, {Locale locale = const Locale('sw')}) => MaterialApp(
      theme: SokoniTheme.light,
      locale: locale,
      supportedLocales: AppLocalizations.supportedLocales,
      localizationsDelegates: const [
        AppLocalizations.delegate,
        GlobalMaterialLocalizations.delegate,
        GlobalWidgetsLocalizations.delegate,
        GlobalCupertinoLocalizations.delegate,
      ],
      home: Scaffold(body: SingleChildScrollView(child: child)),
    );

void main() {
  group('OfferCard', () {
    testWidgets('leads with pay and shows distances, in Swahili by default', (tester) async {
      var accepted = 0;
      var declined = 0;
      await tester.pumpWidget(_app(OfferCard(
        offer: DeliveryOffer.fromJson(offerJson()),
        onAccept: () => accepted++,
        onDecline: () => declined++,
      )));

      expect(find.text('TSh 3,825'), findsOneWidget);
      expect(find.text('Ombi jipya la usafirishaji'), findsOneWidget);
      expect(find.text('1.2 km hadi dukani'), findsOneWidget);
      expect(find.text('Duka la Juma, Ilala'), findsOneWidget);
      expect(find.textContaining('taslimu'), findsNothing);

      await tester.tap(find.text('Kubali'));
      await tester.tap(find.text('Kataa'));
      expect(accepted, 1);
      expect(declined, 1);
    });

    testWidgets('warns about cash to collect, in English', (tester) async {
      await tester.pumpWidget(_app(
        OfferCard(offer: DeliveryOffer.fromJson(offerJson(collectCash: 4500)), onAccept: () {}, onDecline: () {}, moreCount: 2),
        locale: const Locale('en'),
      ));
      expect(find.text('Collect TSh 4,500 cash from the customer'), findsOneWidget);
      expect(find.text('2 more requests'), findsOneWidget);
    });

    testWidgets('accept shows a spinner and cannot be tapped twice', (tester) async {
      var accepted = 0;
      await tester.pumpWidget(_app(OfferCard(
        offer: DeliveryOffer.fromJson(offerJson()),
        accepting: true,
        onAccept: () => accepted++,
        onDecline: () {},
      )));
      await tester.pump(const Duration(milliseconds: 300));
      expect(find.byType(CircularProgressIndicator), findsOneWidget);
      await tester.tap(find.byType(ElevatedButton));
      expect(accepted, 0);
    });
  });

  testWidgets('SuccessCheck draws in and settles', (tester) async {
    await tester.pumpWidget(_app(const Center(child: SuccessCheck())));
    await tester.pump(const Duration(milliseconds: 450));
    await tester.pumpAndSettle();
    expect(find.byType(SuccessCheck), findsOneWidget);
  });

  testWidgets('SuccessCheck respects reduced motion', (tester) async {
    await tester.pumpWidget(MediaQuery(
      data: const MediaQueryData(disableAnimations: true),
      child: _app(const Center(child: SuccessCheck())),
    ));
    await tester.pump();
    expect(tester.hasRunningAnimations, isFalse);
  });

  testWidgets('EmptyState always offers its one action', (tester) async {
    var tapped = false;
    await tester.pumpWidget(_app(SizedBox(
      height: 600,
      child: EmptyState(icon: Icons.inbox, title: 'Nothing here', actionLabel: 'Go online', onAction: () => tapped = true),
    )));
    await tester.tap(find.text('Go online'));
    expect(tapped, isTrue);
  });
}
