import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:sokoni/core/motion/story_ring.dart';
import 'package:sokoni/core/theme/colors.dart';

void main() {
  Widget wrap(Widget child) => MaterialApp(home: Scaffold(body: Center(child: child)));

  testWidgets('unseen ring renders in Sokoni yellow', (tester) async {
    await tester.pumpWidget(
      wrap(const StoryRing(seen: false, child: ColoredBox(color: Colors.grey))),
    );
    await tester.pumpAndSettle();

    final container = tester.widget<Container>(find.byType(Container).first);
    final decoration = container.decoration as BoxDecoration;
    final border = decoration.border as Border;
    expect(border.top.color, SokoniColors.sokoniYellow);
  });

  testWidgets('seen ring does not use the yellow accent', (tester) async {
    await tester.pumpWidget(
      wrap(const StoryRing(seen: true, child: ColoredBox(color: Colors.grey))),
    );
    await tester.pumpAndSettle();

    final container = tester.widget<Container>(find.byType(Container).first);
    final decoration = container.decoration as BoxDecoration;
    final border = decoration.border as Border;
    expect(border.top.color, isNot(SokoniColors.sokoniYellow));
  });

  testWidgets('flipping from seen to unseen replays the announce animation without throwing', (tester) async {
    await tester.pumpWidget(
      wrap(const StoryRing(seen: true, child: ColoredBox(color: Colors.grey))),
    );
    await tester.pumpWidget(
      wrap(const StoryRing(seen: false, child: ColoredBox(color: Colors.grey))),
    );
    await tester.pumpAndSettle();

    expect(tester.takeException(), isNull);
  });
}
