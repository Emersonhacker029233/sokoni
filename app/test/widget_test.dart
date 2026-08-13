import 'package:flutter_test/flutter_test.dart';

import 'package:sokoni/main.dart';

void main() {
  testWidgets('splash settles into home and opens the motion gallery', (
    WidgetTester tester,
  ) async {
    await tester.pumpWidget(const SokoniApp());

    // Splash plays first (max 900ms); let it run to completion and settle
    // onto the placeholder home.
    await tester.pumpAndSettle(const Duration(milliseconds: 950));

    expect(find.text('Sokoni'), findsWidgets);

    await tester.tap(find.text('Motion Gallery'));
    await tester.pumpAndSettle();

    expect(find.text('Motion Gallery'), findsWidgets);
  });
}
