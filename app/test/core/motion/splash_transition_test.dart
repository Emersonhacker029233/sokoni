import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:sokoni/core/motion/splash_transition.dart';

/// D5 (tester feedback): "splash animation too plain" — this covers the
/// rebuilt brand moment's actual contract, not its visual specifics
/// (fade/scale curves aren't meaningfully assertable in a widget test):
/// it completes exactly once, respects disableAnimations, is skippable,
/// and — the one thing that caused a real production startup hang before
/// (see DECISIONS.md) — never leaves anything pending past its own
/// declared duration.
void main() {
  Widget wrap(Widget child, {bool disableAnimations = false}) {
    return MediaQuery(
      data: MediaQueryData(size: const Size(400, 800), disableAnimations: disableAnimations),
      child: MaterialApp(home: child),
    );
  }

  test('runs roughly the 3-second brand moment the client asked for', () {
    // D-round 2 (client feedback): "they want more movement and a longer
    // sequence" — this pins the actual number so a future edit can't
    // silently shrink it back toward the old 1.8s cut without a test
    // noticing. SplashScreen's own navigation timer reads this same
    // constant, so pinning it here also protects that contract.
    expect(SokoniSplashTransition.duration, const Duration(milliseconds: 3000));
  });

  testWidgets('calls onComplete exactly once after its full duration', (tester) async {
    var completeCount = 0;
    await tester.pumpWidget(wrap(SokoniSplashTransition(onComplete: () => completeCount++)));

    await tester.pump(SokoniSplashTransition.duration + const Duration(milliseconds: 1));
    await tester.pump();

    expect(completeCount, 1);

    // Nothing left running past completion — this is exactly the shape of
    // bug that once hung startup: an animation that never reaches
    // AnimationStatus.completed leaves a ticking AnimationController
    // alive forever. Pumping further must not call onComplete again.
    await tester.pump(const Duration(milliseconds: 500));
    expect(completeCount, 1);
  });

  testWidgets('respects disableAnimations and completes immediately without playing', (tester) async {
    var completed = false;
    await tester.pumpWidget(
      wrap(SokoniSplashTransition(onComplete: () => completed = true), disableAnimations: true),
    );
    await tester.pump();

    expect(completed, isTrue);
  });

  testWidgets('tapping the splash skips straight to onComplete', (tester) async {
    var completed = false;
    await tester.pumpWidget(wrap(SokoniSplashTransition(onComplete: () => completed = true)));

    // Well before the animation would finish on its own.
    await tester.pump(const Duration(milliseconds: 200));
    expect(completed, isFalse);

    await tester.tap(find.byType(SokoniSplashTransition));
    expect(completed, isTrue);
  });

  testWidgets('renders every letter of the wordmark by the time it finishes', (tester) async {
    await tester.pumpWidget(wrap(SokoniSplashTransition(onComplete: () {})));

    await tester.pump(SokoniSplashTransition.duration + const Duration(milliseconds: 1));
    await tester.pump();

    for (final letter in 'SOKONI'.split('')) {
      expect(find.text(letter), findsWidgets);
    }
  });
}
