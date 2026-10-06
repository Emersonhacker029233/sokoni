import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:sokoni/core/l10n/gen/app_localizations.dart';
import 'package:sokoni/features/auth/presentation/social_sign_in_buttons.dart';

/// Apple App Review rejected an earlier build under Guideline 2.1(a):
/// "Sign in with Apple and Continue with Google were unresponsive when
/// tapped." The previous implementation rendered both buttons always,
/// disabled with a long-press tooltip when unconfigured — this suite
/// locks in the fix: an unconfigured provider's button is never built at
/// all, and the "or continue with phone" divider never shows above an
/// empty social section (BLOCKERS.md item 3).
Future<void> _pump(
  WidgetTester tester, {
  required bool googleConfigured,
  required bool appleConfigured,
}) async {
  await tester.pumpWidget(
    ProviderScope(
      child: MaterialApp(
        localizationsDelegates: AppLocalizations.localizationsDelegates,
        supportedLocales: AppLocalizations.supportedLocales,
        home: Scaffold(
          body: SocialSignInButtons(googleConfigured: googleConfigured, appleConfigured: appleConfigured),
        ),
      ),
    ),
  );
}

void main() {
  testWidgets('renders nothing at all when neither provider is configured', (tester) async {
    await _pump(tester, googleConfigured: false, appleConfigured: false);

    expect(find.byType(OutlinedButton), findsNothing);
    expect(find.text('Continue with Google'), findsNothing);
    expect(find.text('Continue with Apple'), findsNothing);
    // No tooltip either — a disabled button with a tooltip is exactly
    // the "unresponsive when tapped" shape Apple rejected.
    expect(find.byType(Tooltip), findsNothing);
  });

  testWidgets('renders only the Google button when only Google is configured', (tester) async {
    await _pump(tester, googleConfigured: true, appleConfigured: false);

    expect(find.text('Continue with Google'), findsOneWidget);
    expect(find.text('Continue with Apple'), findsNothing);
    final button = tester.widget<OutlinedButton>(find.byType(OutlinedButton));
    expect(button.onPressed, isNotNull, reason: 'a rendered button must be tappable, never disabled');
  });

  testWidgets('renders only the Apple button when only Apple is configured', (tester) async {
    await _pump(tester, googleConfigured: false, appleConfigured: true);

    expect(find.text('Continue with Apple'), findsOneWidget);
    expect(find.text('Continue with Google'), findsNothing);
    final button = tester.widget<OutlinedButton>(find.byType(OutlinedButton));
    expect(button.onPressed, isNotNull);
  });

  testWidgets('renders both buttons, both tappable, when both are configured', (tester) async {
    await _pump(tester, googleConfigured: true, appleConfigured: true);

    expect(find.text('Continue with Google'), findsOneWidget);
    expect(find.text('Continue with Apple'), findsOneWidget);
    for (final element in tester.widgetList<OutlinedButton>(find.byType(OutlinedButton))) {
      expect(element.onPressed, isNotNull);
    }
  });
}
