import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:sokoni/core/motion/double_tap_to_save.dart';

void main() {
  testWidgets('a double tap calls onSave exactly once and never calls onSingleTap', (tester) async {
    var saveCalls = 0;
    var singleTapCalls = 0;

    await tester.pumpWidget(
      MaterialApp(
        home: DoubleTapToSave(
          onSave: () => saveCalls++,
          onSingleTap: () => singleTapCalls++,
          child: const SizedBox(width: 200, height: 200, child: ColoredBox(color: Colors.grey)),
        ),
      ),
    );

    final finder = find.byType(DoubleTapToSave);
    await tester.tap(finder);
    await tester.pump(const Duration(milliseconds: 50));
    await tester.tap(finder);
    await tester.pumpAndSettle();

    expect(saveCalls, 1, reason: 'a genuine double tap must save exactly once');
    expect(singleTapCalls, 0, reason: 'a double tap must not also fire the single-tap action (would double-navigate/toggle)');
  });

  testWidgets('an isolated single tap still fires onSingleTap, with no onSave call', (tester) async {
    var saveCalls = 0;
    var singleTapCalls = 0;

    await tester.pumpWidget(
      MaterialApp(
        home: DoubleTapToSave(
          onSave: () => saveCalls++,
          onSingleTap: () => singleTapCalls++,
          child: const SizedBox(width: 200, height: 200, child: ColoredBox(color: Colors.grey)),
        ),
      ),
    );

    await tester.tap(find.byType(DoubleTapToSave));
    // Past the platform's own tap-vs-double-tap disambiguation window
    // (kDoubleTapTimeout, 300ms) with no second tap — must resolve to a
    // single, isolated tap.
    await tester.pumpAndSettle(const Duration(milliseconds: 400));

    expect(singleTapCalls, 1);
    expect(saveCalls, 0);
  });

  testWidgets('double-tapping plays the heart overlay unless animations are disabled', (tester) async {
    await tester.pumpWidget(
      MaterialApp(
        home: DoubleTapToSave(
          onSave: () {},
          child: const SizedBox(width: 200, height: 200, child: ColoredBox(color: Colors.grey)),
        ),
      ),
    );

    expect(find.byIcon(Icons.favorite_rounded), findsNothing, reason: 'no heart before any interaction');

    final finder = find.byType(DoubleTapToSave);
    await tester.tap(finder);
    await tester.pump(const Duration(milliseconds: 50));
    await tester.tap(finder);
    await tester.pump();

    expect(find.byIcon(Icons.favorite_rounded), findsOneWidget, reason: 'the heart appears once the double tap is recognised');

    await tester.pumpAndSettle();
    expect(find.byIcon(Icons.favorite_rounded), findsNothing, reason: 'the heart fades out and fully disappears once its ~800ms sequence completes');
  });

  testWidgets('MediaQuery.disableAnimations still saves but skips the heart overlay entirely', (tester) async {
    var saveCalls = 0;

    await tester.pumpWidget(
      MediaQuery(
        data: const MediaQueryData(disableAnimations: true),
        child: MaterialApp(
          home: DoubleTapToSave(
            onSave: () => saveCalls++,
            child: const SizedBox(width: 200, height: 200, child: ColoredBox(color: Colors.grey)),
          ),
        ),
      ),
    );

    final finder = find.byType(DoubleTapToSave);
    await tester.tap(finder);
    await tester.pump(const Duration(milliseconds: 50));
    await tester.tap(finder);
    await tester.pumpAndSettle();

    expect(saveCalls, 1, reason: 'disabling animations must not skip the actual save');
    expect(find.byIcon(Icons.favorite_rounded), findsNothing);
  });
}
