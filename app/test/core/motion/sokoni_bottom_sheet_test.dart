import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:sokoni/core/motion/sokoni_bottom_sheet.dart';

/// D1 (tester feedback): `showSokoniBottomSheet` is the one shared
/// primitive nearly every sheet in the app is built from — it previously
/// had no SafeArea handling at all, so a gesture-nav device's
/// home-indicator strip could sit right on top of a sheet's own last
/// button/field.
void main() {
  Future<void> openSheet(WidgetTester tester, double bottomInset) async {
    await tester.pumpWidget(
      MediaQuery(
        data: MediaQueryData(viewPadding: EdgeInsets.only(bottom: bottomInset), padding: EdgeInsets.only(bottom: bottomInset)),
        child: MaterialApp(
          home: Builder(
            builder: (context) => Scaffold(
              body: Center(
                child: ElevatedButton(
                  onPressed: () => showSokoniBottomSheet<void>(
                    context: context,
                    initialChildSize: 0.3,
                    builder: (context) => const SizedBox(
                      height: 40,
                      child: Text('Sheet content', key: Key('sheet-content')),
                    ),
                  ),
                  child: const Text('Open'),
                ),
              ),
            ),
          ),
        ),
      ),
    );
    await tester.tap(find.text('Open'));
    await tester.pumpAndSettle();
  }

  testWidgets('the sheet reserves the devices own bottom inset rather than ignoring it', (tester) async {
    await openSheet(tester, 24);

    // A direct structural check, not a geometry proxy: DraggableScrollableSheet's
    // fractional sizing and a short scrollable child made a bottom-position
    // measurement here pass regardless of whether the inset was actually
    // reserved — this asserts the actual fix (a bottom-only SafeArea
    // wrapping the sheet's own content) is present instead.
    final safeArea = tester.widget<SafeArea>(find.byType(SafeArea));
    expect(safeArea.bottom, isTrue);
    expect(safeArea.top, isFalse);
  });

  testWidgets('on an insetless device, the sheet still opens and renders its content', (tester) async {
    await openSheet(tester, 0);

    expect(find.byKey(const Key('sheet-content')), findsOneWidget);
  });
}
