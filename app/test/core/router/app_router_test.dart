import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';

import 'package:sokoni/core/router/app_router.dart';

/// Part 2 (client feedback): "shared product links don't open" — when
/// Android's App Links intent-filter hands the app a real
/// https://sokoni.co.tz/p/{id}/{slug} URL (see AndroidManifest.xml), the
/// app must resolve it to the same product screen a normal in-app tap
/// would reach, not 404 or land on some unrelated screen.
void main() {
  GoRouter buildRouter() => GoRouter(
    initialLocation: '/start',
    routes: [
      GoRoute(path: '/start', builder: (context, state) => const SizedBox.shrink()),
      GoRoute(
        path: '/products/:id',
        builder: (context, state) => Text('PRODUCT ${state.pathParameters['id']}'),
      ),
      GoRoute(path: '/p/:id', redirect: (context, state) => sharedProductLinkRedirect(state)),
      GoRoute(path: '/p/:id/:slug', redirect: (context, state) => sharedProductLinkRedirect(state)),
    ],
  );

  testWidgets('a bare /p/:id link resolves to the same product screen an in-app tap would', (tester) async {
    final router = buildRouter();
    await tester.pumpWidget(MaterialApp.router(routerConfig: router));

    router.go('/p/42');
    await tester.pumpAndSettle();

    expect(find.text('PRODUCT 42'), findsOneWidget);
  });

  testWidgets('a slugged /p/:id/:slug link (the real shape the website shares) resolves the same way', (tester) async {
    final router = buildRouter();
    await tester.pumpWidget(MaterialApp.router(routerConfig: router));

    router.go('/p/42/some-product-title');
    await tester.pumpAndSettle();

    expect(find.text('PRODUCT 42'), findsOneWidget);
  });

  testWidgets('a tampered/non-numeric id falls back to home instead of crashing', (tester) async {
    final router = buildRouter();
    await tester.pumpWidget(MaterialApp.router(routerConfig: router));

    router.go('/p/not-a-number');
    await tester.pumpAndSettle();

    expect(tester.takeException(), isNull);
    expect(find.text('PRODUCT not-a-number'), findsNothing);
  });
}
