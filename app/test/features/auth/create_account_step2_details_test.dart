import 'dart:async';

import 'package:dio/dio.dart';
import 'package:drift/native.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:sokoni/core/l10n/gen/app_localizations.dart';
import 'package:sokoni/core/providers.dart';
import 'package:sokoni/core/storage/app_database.dart';
import 'package:sokoni/core/storage/secure_storage.dart';
import 'package:sokoni/data/api/auth_api.dart';
import 'package:sokoni/data/api/seller_api.dart';
import 'package:sokoni/data/repositories/auth_repository.dart';
import 'package:sokoni/data/repositories/seller_repository.dart';
import 'package:sokoni/features/auth/presentation/create_account/step2_details.dart';
import 'package:sokoni/features/auth/providers/auth_providers.dart';
import 'package:sokoni/features/seller/providers/seller_providers.dart';

/// Simulates the exact production incident: `/auth/check-phone` doesn't
/// exist server-side, so the real client would see a 404 — represented
/// here as any thrown exception, since the bug this covers is "the
/// widget never catches whatever the repository throws", not the
/// specific exception type.
class _ThrowingAuthRepository extends AuthRepository {
  _ThrowingAuthRepository() : super(api: AuthApi(Dio()), storage: SokoniSecureStorage());

  @override
  Future<bool> checkPhoneExists(String phoneE164) => Future.error(Exception('404 route not found'));
}

/// Never resolves — simulates a genuinely hung/unreachable endpoint
/// rather than a fast error, to prove the timeout (not just the
/// try/catch) is what saves the button. `testWidgets`' own fake clock
/// (`AutomatedTestWidgetsFlutterBinding`) fires real `Timer`s —
/// including the `.timeout()` this screen applies — as `tester.pump`
/// advances virtual time, so no real 6-second wait is needed.
class _HangingAuthRepository extends AuthRepository {
  _HangingAuthRepository() : super(api: AuthApi(Dio()), storage: SokoniSecureStorage());

  @override
  Future<bool> checkPhoneExists(String phoneE164) => Completer<bool>().future;
}

class _ScriptedAuthRepository extends AuthRepository {
  _ScriptedAuthRepository({required this.registeredPhone}) : super(api: AuthApi(Dio()), storage: SokoniSecureStorage());

  final String registeredPhone;

  @override
  Future<bool> checkPhoneExists(String phoneE164) async => phoneE164 == registeredPhone;
}

class _NoOpSellerRepository extends SellerRepository {
  _NoOpSellerRepository() : super(api: SellerApi(Dio()), dio: Dio());

  @override
  Future<bool> checkHandleAvailable(String handle) async => true;
}

Future<void> _pump(WidgetTester tester, {required AuthRepository authRepository, required VoidCallback onSubmitted}) {
  return tester.pumpWidget(
    ProviderScope(
      overrides: [
        appDatabaseProvider.overrideWithValue(AppDatabase.forTesting(NativeDatabase.memory())),
        authRepositoryProvider.overrideWithValue(authRepository),
        sellerRepositoryProvider.overrideWithValue(_NoOpSellerRepository()),
      ],
      child: MaterialApp(
        localizationsDelegates: AppLocalizations.localizationsDelegates,
        supportedLocales: AppLocalizations.supportedLocales,
        home: Scaffold(body: CreateAccountStep2Details(onSubmitted: onSubmitted)),
      ),
    ),
  );
}

void main() {
  testWidgets(
    'a phone check that errors (e.g. the endpoint 404ing in production) fails open — '
    'Next still works, regression for the actual incident',
    (tester) async {
      var submitted = false;
      await _pump(tester, authRepository: _ThrowingAuthRepository(), onSubmitted: () => submitted = true);

      await tester.enterText(find.widgetWithText(TextFormField, 'Full name'), 'Amina Buyer');
      await tester.enterText(find.widgetWithText(TextFormField, 'Phone number'), '0754123456');
      // Flush the 500ms debounce, the failed request, and the catch's setState.
      await tester.pump(const Duration(milliseconds: 600));
      await tester.pump();
      await tester.pump();

      expect(find.byIcon(Icons.error_rounded), findsNothing, reason: 'a failed check is not a confirmed problem');

      await tester.tap(find.text('Next'));
      await tester.pump();

      expect(submitted, isTrue, reason: 'Next must still work after a broken live check — this was the actual bug');
    },
  );

  testWidgets(
    'a phone check that never resolves times out and fails open, rather than leaving Next dead forever',
    (tester) async {
      var submitted = false;
      await _pump(tester, authRepository: _HangingAuthRepository(), onSubmitted: () => submitted = true);

      await tester.enterText(find.widgetWithText(TextFormField, 'Full name'), 'Amina Buyer');
      await tester.enterText(find.widgetWithText(TextFormField, 'Phone number'), '0754123456');

      // Past the 500ms debounce but nowhere near the 6s check timeout yet.
      await tester.pump(const Duration(seconds: 1));
      await tester.tap(find.text('Next'));
      await tester.pump();
      expect(submitted, isFalse, reason: 'still within the timeout window — the check has not failed open yet');

      // Past the 6s timeout — the check must have given up and failed open.
      await tester.pump(const Duration(seconds: 6));
      await tester.tap(find.text('Next'));
      await tester.pump();
      expect(submitted, isTrue, reason: 'a check that never resolves must not permanently disable Next');
    },
  );

  testWidgets('a CONFIRMED already-registered phone still correctly blocks Next', (tester) async {
    var submitted = false;
    await _pump(
      tester,
      authRepository: _ScriptedAuthRepository(registeredPhone: '+255754123456'),
      onSubmitted: () => submitted = true,
    );

    await tester.enterText(find.widgetWithText(TextFormField, 'Full name'), 'Amina Buyer');
    await tester.enterText(find.widgetWithText(TextFormField, 'Phone number'), '0754123456');
    await tester.pump(const Duration(milliseconds: 600));
    await tester.pump();
    await tester.pump();

    expect(find.byIcon(Icons.error_rounded), findsOneWidget, reason: 'a real duplicate must still show as a confirmed problem');

    await tester.tap(find.text('Next'));
    await tester.pump();

    expect(submitted, isFalse, reason: 'a genuinely already-registered number must still block — only broken checks fail open');
  });

  /// Part 2 (client feedback): "+255 shown as a fixed, non-editable prefix" — same treatment as the sign-in sheet's own phone field.
  testWidgets('the phone field shows a fixed +255 prefix', (tester) async {
    await _pump(tester, authRepository: _ScriptedAuthRepository(registeredPhone: '+255700000000'), onSubmitted: () {});

    final textField = tester.widget<TextField>(
      find.descendant(of: find.widgetWithText(TextFormField, 'Phone number'), matching: find.byType(TextField)),
    );
    expect(textField.decoration?.prefixText, '+255 ');
  });
}
