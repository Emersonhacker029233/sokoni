import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';

import 'package:sokoni/core/motion/hero_image_transition.dart';

/// Part 1 (client feedback, "product opens from search but never loads"):
/// the actual, confirmed cause was a `SokoniHeroImage.flightShuttleBuilder`
/// bug, not a network/JSON-shape issue — a Hero's own `.child` is the
/// `ClipRRect` this class wraps its child in, never the `SokoniHeroImage`
/// itself, so `(fromContext.widget as Hero).child as SokoniHeroImage`
/// threw a real `TypeError` on every Hero flight this class drives
/// (every product card → detail transition with a matching tag — search
/// results and favourites both go through it; the home feed doesn't,
/// since `FeedCard` has no Hero at all, which is why only some paths
/// looked broken). Reproduced with an actual push through a
/// `StatefulShellRoute` branch (search's real navigator topology) before
/// fixing, confirmed clean after.
void main() {
  testWidgets('a Hero flight through SokoniHeroImage completes with no exception', (tester) async {
    final shellNavKey = GlobalKey<NavigatorState>(debugLabel: 'shell-branch');
    final rootNavKey = GlobalKey<NavigatorState>(debugLabel: 'root');

    final router = GoRouter(
      navigatorKey: rootNavKey,
      initialLocation: '/search',
      routes: [
        GoRoute(
          path: '/detail',
          parentNavigatorKey: rootNavKey,
          pageBuilder: (context, state) => const MaterialPage(
            child: Scaffold(
              body: SokoniHeroImage(tag: 'x', child: Material(child: Text('DETAIL CONTENT'))),
            ),
          ),
        ),
        StatefulShellRoute.indexedStack(
          builder: (context, state, shell) => shell,
          branches: [
            StatefulShellBranch(
              navigatorKey: shellNavKey,
              routes: [GoRoute(path: '/home', builder: (context, state) => const Text('HOME'))],
            ),
            StatefulShellBranch(
              routes: [
                GoRoute(
                  path: '/search',
                  builder: (context, state) => Scaffold(
                    body: GestureDetector(
                      onTap: () => context.push('/detail'),
                      child: const SokoniHeroImage(tag: 'x', child: Material(child: Text('SEARCH CARD'))),
                    ),
                  ),
                ),
              ],
            ),
          ],
        ),
      ],
    );

    await tester.pumpWidget(MaterialApp.router(routerConfig: router));
    await tester.pumpAndSettle();

    await tester.tap(find.text('SEARCH CARD'));
    // Pump through the whole flight one frame at a time (rather than
    // pumpAndSettle) — the original bug threw on every single frame of
    // the shuttle, not just at the start or end of it.
    for (var i = 0; i < 10; i++) {
      await tester.pump(const Duration(milliseconds: 50));
    }
    await tester.pumpAndSettle();

    expect(tester.takeException(), isNull);
    expect(find.text('DETAIL CONTENT'), findsOneWidget);
  });
}
