import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';

import 'package:dio/dio.dart';

import 'package:sokoni/core/l10n/gen/app_localizations.dart';
import 'package:sokoni/core/network/api_exception.dart';
import 'package:sokoni/core/storage/secure_storage.dart';
import 'package:sokoni/data/api/auth_api.dart';
import 'package:sokoni/data/models/user.dart';
import 'package:sokoni/data/repositories/auth_repository.dart';
import 'package:sokoni/features/auth/providers/auth_providers.dart';
import 'package:sokoni/features/legal/presentation/terms_acceptance_screen.dart';

/// Fakes [AuthRepository.acceptTerms] without a real Dio client — the API
/// call itself is already covered server-side (see
/// `api/tests/Feature/Auth/TermsAcceptanceTest.php`); what this test
/// guards is purely the screen's own navigation behaviour once that call
/// succeeds or fails.
class _FakeAuthRepository extends AuthRepository {
  _FakeAuthRepository({required this.shouldSucceed})
    : super(api: AuthApi(Dio()), storage: SokoniSecureStorage());

  final bool shouldSucceed;

  @override
  Future<SokoniUser> acceptTerms(String version) async {
    if (!shouldSucceed) {
      throw const ServerException();
    }
    return const SokoniUser(id: 1, name: 'Test User', isSeller: false, termsAccepted: true);
  }
}

/// Real screens reach [TermsAcceptanceScreen] via `context.push` under a
/// go_router `GoRouter` (see `ensureTermsAccepted` in
/// `features/legal/presentation/legal_gate.dart`), and its own success
/// path pops via go_router's `context.pop()`, not the raw `Navigator` —
/// so this test needs a real `GoRouter` in the tree, not a bare
/// `MaterialApp`/`Navigator.push`, or `context.pop()` would throw
/// "no GoRouter found" instead of exercising the real pop path.
Future<void> _pumpScreen(WidgetTester tester, {required bool shouldSucceed}) async {
  final router = GoRouter(
    initialLocation: '/',
    routes: [
      GoRoute(
        path: '/',
        builder: (context, state) => Scaffold(
          body: Center(
            child: ElevatedButton(
              child: const Text('open'),
              onPressed: () => context.push('/terms-acceptance'),
            ),
          ),
        ),
      ),
      GoRoute(
        path: '/terms-acceptance',
        builder: (context, state) => const TermsAcceptanceScreen(),
      ),
    ],
  );

  await tester.pumpWidget(
    ProviderScope(
      overrides: [authRepositoryProvider.overrideWithValue(_FakeAuthRepository(shouldSucceed: shouldSucceed))],
      child: MaterialApp.router(
        routerConfig: router,
        localizationsDelegates: AppLocalizations.localizationsDelegates,
        supportedLocales: AppLocalizations.supportedLocales,
      ),
    ),
  );

  await tester.tap(find.text('open'));
  await tester.pumpAndSettle();
}

void main() {
  testWidgets('accepting terms successfully closes the screen (regression: '
      'PopScope(canPop: false) must not block the screen\'s own success pop)', (tester) async {
    await _pumpScreen(tester, shouldSucceed: true);

    expect(find.byType(TermsAcceptanceScreen), findsOneWidget);

    await tester.tap(find.byType(CheckboxListTile));
    await tester.pump();
    await tester.tap(find.byType(FilledButton));
    await tester.pumpAndSettle();

    expect(find.byType(TermsAcceptanceScreen), findsNothing);
    expect(find.text('open'), findsOneWidget);
  });

  testWidgets('a failed acceptance stays on screen and shows a visible error, never a silent return', (
    tester,
  ) async {
    await _pumpScreen(tester, shouldSucceed: false);

    await tester.tap(find.byType(CheckboxListTile));
    await tester.pump();
    await tester.tap(find.byType(FilledButton));
    await tester.pumpAndSettle();

    expect(find.byType(TermsAcceptanceScreen), findsOneWidget);
    expect(find.text(const ServerException().message), findsOneWidget);

    // The button must be usable again — a real retry, not a dead end.
    expect(tester.widget<FilledButton>(find.byType(FilledButton)).onPressed, isNotNull);
  });
}
