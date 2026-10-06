import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:sokoni/core/l10n/gen/app_localizations.dart';
import 'package:sokoni/features/auth/presentation/auth_entry_sheet.dart';

/// Neither Google nor Apple is configured in a plain test binary (no
/// `--dart-define`s passed) — the same state a real, not-yet-configured
/// build is in. This locks in that the sheet degrades cleanly in that
/// state: no orphaned "or" divider sitting above an empty social section
/// (BLOCKERS.md item 3, the Guideline 2.1(a) rejection fix).
void main() {
  testWidgets('shows no social section or divider when neither provider is configured', (tester) async {
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

    expect(find.byType(Divider), findsNothing);
    expect(find.text('Continue with Google'), findsNothing);
    expect(find.text('Continue with Apple'), findsNothing);
    // The rest of the sheet still renders normally.
    expect(find.text('Sign in'), findsOneWidget);
  });
}
