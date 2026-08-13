import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:sokoni/main.dart';

void main() {
  testWidgets('splash settles into the home tab shell', (WidgetTester tester) async {
    await tester.pumpWidget(const ProviderScope(child: SokoniApp()));

    // Splash plays first (max 900ms); let it run to completion and land on
    // the home tab of the bottom-nav shell.
    await tester.pumpAndSettle(const Duration(milliseconds: 950));

    expect(find.text('Sokoni'), findsWidgets);
  });
}
