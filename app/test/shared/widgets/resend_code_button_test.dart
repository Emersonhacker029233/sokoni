import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:sokoni/core/l10n/gen/app_localizations.dart';
import 'package:sokoni/core/network/api_exception.dart';
import 'package:sokoni/shared/widgets/resend_code_button.dart';

Widget _wrap(Widget child) {
  // Forces a deterministic, locale-independent "14:30" rendering from
  // TimeOfDay.format() — otherwise this depends on the test
  // environment's default locale choosing 12-hour vs 24-hour display.
  return MediaQuery(
    data: const MediaQueryData(alwaysUse24HourFormat: true),
    child: MaterialApp(
      localizationsDelegates: AppLocalizations.localizationsDelegates,
      supportedLocales: AppLocalizations.supportedLocales,
      home: Scaffold(body: child),
    ),
  );
}

void main() {
  /// Part 3 (client feedback): "respect the existing server-side rate
  /// limit... and say so clearly when the limit is reached rather than
  /// failing silently." Isolated from the real HTTP layer — onResend
  /// throwing RateLimitedException is exactly what a real 429 maps to
  /// (see dio_client.dart's _mapResponse).
  testWidgets('a rate-limit error from onResend shows the servers own message, not a silent failure', (tester) async {
    await tester.pumpWidget(
      _wrap(
        ResendCodeButton(
          codeExpiresAt: DateTime.now().add(const Duration(minutes: 5)),
          onResend: () async => throw const RateLimitedException("You've requested too many codes. Please try again in 12 minutes."),
        ),
      ),
    );

    await tester.pump(const Duration(seconds: 60));
    await tester.tap(find.text('Resend code'));
    await tester.pump();
    await tester.pump();

    expect(find.text("You've requested too many codes. Please try again in 12 minutes."), findsOneWidget);
    // Still stuck on the same failed attempt — must not silently look like a success.
    expect(find.text('A new code has been sent.'), findsNothing);
  });

  testWidgets('shows the codes real expiry time', (tester) async {
    final expiresAt = DateTime(2026, 1, 1, 14, 30);

    await tester.pumpWidget(
      _wrap(ResendCodeButton(codeExpiresAt: expiresAt, onResend: () async => expiresAt)),
    );

    expect(find.textContaining('14:30'), findsOneWidget);
  });

  testWidgets('a successful resend updates the expiry to the fresh one and restarts the countdown', (tester) async {
    final firstExpiry = DateTime(2026, 1, 1, 14, 30);
    final secondExpiry = DateTime(2026, 1, 1, 14, 35);

    await tester.pumpWidget(
      _wrap(ResendCodeButton(codeExpiresAt: firstExpiry, onResend: () async => secondExpiry)),
    );

    expect(find.textContaining('14:30'), findsOneWidget);

    await tester.pump(const Duration(seconds: 60));
    await tester.tap(find.text('Resend code'));
    await tester.pump();
    await tester.pump();

    expect(find.textContaining('14:35'), findsOneWidget);
    expect(find.text('A new code has been sent.'), findsOneWidget);
    // Countdown restarted — resend is disabled again immediately after succeeding.
    expect(find.text('Resend code'), findsNothing);
  });
}
