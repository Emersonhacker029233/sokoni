import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:sokoni/core/l10n/gen/app_localizations.dart';
import 'package:sokoni/features/auth/presentation/auth_entry_sheet.dart';

Future<void> _openSheet(WidgetTester tester) async {
  await tester.pumpWidget(
    ProviderScope(
      child: MaterialApp(
        localizationsDelegates: AppLocalizations.localizationsDelegates,
        supportedLocales: AppLocalizations.supportedLocales,
        home: Builder(
          builder: (context) => Scaffold(
            body: Center(
              child: ElevatedButton(
                onPressed: () => showAuthEntrySheet(context),
                child: const Text('open'),
              ),
            ),
          ),
        ),
      ),
    ),
  );

  await tester.tap(find.text('open'));
  await tester.pumpAndSettle();
}

void main() {
  /// Apple Guideline 1.2 (rejection of iOS 1.0 (7)): users agree to terms
  /// that say there is zero tolerance for objectionable content and
  /// abusive users before they can sign in or register — so no way in is
  /// on screen until the one Agree button is tapped.
  testWidgets('the Terms come first, with no way to sign in or register until Agree', (tester) async {
    await _openSheet(tester);

    expect(find.text('Sokoni Terms of Use'), findsOneWidget);
    expect(find.text('Zero tolerance'), findsOneWidget);
    expect(find.textContaining('objectionable content or abusive users'), findsOneWidget);
    expect(find.text('Read the full Terms of Use'), findsOneWidget);
    expect(find.byType(Checkbox), findsNothing, reason: 'one clear Agree action, not a checkbox and a button');
    expect(find.text('Sign in'), findsNothing);
    expect(find.text('Create an account'), findsNothing);

    await tester.ensureVisible(find.text('Agree and continue'));
    await tester.tap(find.text('Agree and continue'));
    await tester.pumpAndSettle();

    expect(find.text('Sign in'), findsOneWidget);
    expect(find.text('Sokoni Terms of Use'), findsNothing);
  });

  /// Neither Google nor Apple is configured in a plain test binary (no
  /// `--dart-define`s passed) — exactly how iOS 1.0 (7) was built, and how
  /// 1.0 (9) must be built too. This locks in that the sheet degrades
  /// cleanly in that state: no orphaned "or" divider sitting above an
  /// empty social section (BLOCKERS.md item 3, the Guideline 2.1(a)
  /// rejection fix).
  testWidgets('shows no social section or divider when neither provider is configured', (tester) async {
    await _openSheet(tester);
    await tester.ensureVisible(find.text('Agree and continue'));
    await tester.tap(find.text('Agree and continue'));
    await tester.pumpAndSettle();

    expect(find.byType(Divider), findsNothing);
    expect(find.text('Continue with Google'), findsNothing);
    expect(find.text('Continue with Apple'), findsNothing);
    // The rest of the sheet still renders normally.
    expect(find.text('Sign in'), findsOneWidget);
  });

  testWidgets('agreeing once is remembered for the rest of the session', (tester) async {
    await _openSheet(tester);
    await tester.ensureVisible(find.text('Agree and continue'));
    await tester.tap(find.text('Agree and continue'));
    await tester.pumpAndSettle();

    Navigator.of(tester.element(find.text('Sign in'))).pop();
    await tester.pumpAndSettle();
    await tester.tap(find.text('open'));
    await tester.pumpAndSettle();

    expect(find.text('Sign in'), findsOneWidget);
    expect(find.text('Agree and continue'), findsNothing);
  });
}
