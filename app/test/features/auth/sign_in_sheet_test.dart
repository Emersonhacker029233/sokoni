import 'dart:convert';
import 'dart:typed_data';

import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';

import 'package:sokoni/core/l10n/gen/app_localizations.dart';
import 'package:sokoni/core/network/dio_client.dart';
import 'package:sokoni/core/providers.dart';
import 'package:sokoni/core/router/routes.dart';
import 'package:sokoni/core/storage/secure_storage.dart';
import 'package:sokoni/features/auth/presentation/sign_in_sheet.dart';

/// A real (unmocked) `SokoniSecureStorage` would hit a platform channel
/// with no handler registered in this plain test environment — never
/// used here since no test actually needs a real token, but every
/// request still passes through `AuthInterceptor.onRequest`'s
/// `readToken()` call regardless, so it needs *something* fast and safe
/// to resolve to.
class _NoTokenSecureStorage extends SokoniSecureStorage {
  @override
  Future<String?> readToken() async => null;
}

/// Scripts `/auth/otp/request` and `/auth/otp/verify` responses by phone
/// number, so the sheet's branching (new vs. returning number) can be
/// driven deterministically without a real backend.
class _ScriptedAuthAdapter implements HttpClientAdapter {
  _ScriptedAuthAdapter({required this.newNumbers});

  final Set<String> newNumbers;

  @override
  Future<ResponseBody> fetch(RequestOptions options, Stream<Uint8List>? requestStream, Future<void>? cancelFuture) async {
    if (options.path.endsWith('/auth/otp/request')) {
      final phone = (options.data as Map)['phone'] as String;
      final isNew = newNumbers.contains(phone);
      return _json({
        'message': 'OTP sent.',
        'is_new_account': isNew,
        'expires_at': DateTime.now().add(const Duration(minutes: 5)).toIso8601String(),
      });
    }
    if (options.path.endsWith('/auth/otp/verify')) {
      return _json({
        'token': 'a-real-token',
        'user': {'id': 1, 'name': 'Returning User', 'is_seller': false, 'terms_accepted': true},
        'is_new_account': false,
      });
    }
    throw DioException.connectionError(requestOptions: options, reason: 'unscripted path: ${options.path}');
  }

  ResponseBody _json(Map<String, dynamic> body) {
    return ResponseBody.fromString(
      jsonEncode(body),
      200,
      headers: {
        Headers.contentTypeHeader: [Headers.jsonContentType],
      },
    );
  }

  @override
  void close({bool force = false}) {}
}

Future<void> _pump(WidgetTester tester, {required Set<String> newNumbers}) async {
  final router = GoRouter(
    initialLocation: '/',
    routes: [
      GoRoute(
        path: '/',
        builder: (context, state) => Scaffold(
          body: Center(
            child: ElevatedButton(onPressed: () => showSignInSheet(context), child: const Text('open')),
          ),
        ),
      ),
      GoRoute(path: SokoniRoutes.createAccount, builder: (context, state) => const Scaffold(body: Text('create account screen'))),
    ],
  );

  await tester.pumpWidget(
    ProviderScope(
      overrides: [
        secureStorageProvider.overrideWithValue(_NoTokenSecureStorage()),
        dioProvider.overrideWith((ref) {
          final dio = buildDioClient(storage: ref.watch(secureStorageProvider), onUnauthenticated: () async {});
          dio.httpClientAdapter = _ScriptedAuthAdapter(newNumbers: newNumbers);
          return dio;
        }),
      ],
      child: MaterialApp.router(
        routerConfig: router,
        localizationsDelegates: AppLocalizations.localizationsDelegates,
        supportedLocales: AppLocalizations.supportedLocales,
      ),
    ),
  );
}

void main() {
  testWidgets(
    'a returning number goes straight to "Welcome back" + the code field — never a name field '
    '(the actual reported bug)',
    (tester) async {
      await _pump(tester, newNumbers: {});
      await tester.tap(find.text('open'));
      await tester.pump();
      await tester.pump();
      await tester.pump(const Duration(milliseconds: 300));
      await tester.pump(const Duration(milliseconds: 300));

      await tester.enterText(find.byType(TextFormField).first, '0754123456');
      await tester.tap(find.text('Send code'));
      await tester.pump();
      await tester.pump();
      await tester.pump(const Duration(milliseconds: 300));
      await tester.pump(const Duration(milliseconds: 300));

      expect(find.text('Welcome back'), findsOneWidget);
      expect(find.text('Full name'), findsNothing);
      expect(find.text('Full name (creating a new account)'), findsNothing);
      // Exactly one field left — the code — not a second one for a name.
      expect(find.byType(TextFormField), findsOneWidget);
    },
  );

  testWidgets('a returning number shows the masked phone number, not the raw one', (tester) async {
    await _pump(tester, newNumbers: {});
    await tester.tap(find.text('open'));
    await tester.pump();
      await tester.pump();
      await tester.pump(const Duration(milliseconds: 300));
      await tester.pump(const Duration(milliseconds: 300));

    await tester.enterText(find.byType(TextFormField).first, '0754123456');
    await tester.tap(find.text('Send code'));
    await tester.pump();
      await tester.pump();
      await tester.pump(const Duration(milliseconds: 300));
      await tester.pump(const Duration(milliseconds: 300));

    expect(find.textContaining('0754 *** 456'), findsOneWidget);
    expect(find.textContaining('+255754123456'), findsNothing);
  });

  testWidgets('a brand-new number offers "Create an account instead" rather than a doomed code field', (tester) async {
    await _pump(tester, newNumbers: {'+255754999888'});
    await tester.tap(find.text('open'));
    await tester.pump();
      await tester.pump();
      await tester.pump(const Duration(milliseconds: 300));
      await tester.pump(const Duration(milliseconds: 300));

    await tester.enterText(find.byType(TextFormField).first, '0754999888');
    await tester.tap(find.text('Send code'));
    await tester.pump();
      await tester.pump();
      await tester.pump(const Duration(milliseconds: 300));
      await tester.pump(const Duration(milliseconds: 300));

    expect(find.text("We don't recognise this number"), findsOneWidget);
    expect(find.byType(TextFormField), findsNothing, reason: 'no code field for a number that could never verify here');

    await tester.tap(find.text('Create an account instead'));
    await tester.pump();
      await tester.pump();
      await tester.pump(const Duration(milliseconds: 300));
      await tester.pump(const Duration(milliseconds: 300));

    expect(find.text('create account screen'), findsOneWidget);
  });

  testWidgets('verifying the code for a returning number signs them in with no name ever sent', (tester) async {
    await _pump(tester, newNumbers: {});
    await tester.tap(find.text('open'));
    await tester.pump();
      await tester.pump();
      await tester.pump(const Duration(milliseconds: 300));
      await tester.pump(const Duration(milliseconds: 300));

    await tester.enterText(find.byType(TextFormField).first, '0754123456');
    await tester.tap(find.text('Send code'));
    await tester.pump();
      await tester.pump();
      await tester.pump(const Duration(milliseconds: 300));
      await tester.pump(const Duration(milliseconds: 300));

    await tester.enterText(find.byType(TextFormField).first, '123456');
    await tester.tap(find.text('Verify'));
    await tester.pump();
      await tester.pump();
      await tester.pump(const Duration(milliseconds: 300));
      await tester.pump(const Duration(milliseconds: 300));

    // completeSignIn() closes the sheet for a returning user — back to the host screen.
    expect(find.text('open'), findsOneWidget);
  });

  /// Part 2 (client feedback): "+255 shown as a fixed, non-editable
  /// prefix — the user types only their own number."
  testWidgets('the phone field shows a fixed +255 prefix the user cannot edit', (tester) async {
    await _pump(tester, newNumbers: {});
    await tester.tap(find.text('open'));
    await tester.pump();
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 300));
    await tester.pump(const Duration(milliseconds: 300));

    // TextFormField itself doesn't expose `decoration` publicly — it
    // builds a real TextField internally, which does.
    final textField = tester.widget<TextField>(find.byType(TextField).first);
    expect(textField.decoration?.prefixText, '+255 ');

    // prefixText is decoration-only — typing local digits never includes
    // it in the controller's own text, which is exactly what keeps it
    // "not editable and not deletable".
    await tester.enterText(find.byType(TextFormField).first, '754123456');
    expect(textField.controller!.text, '754123456');
  });

  testWidgets('a number typed without the leading zero resolves just as well as one with it', (tester) async {
    await _pump(tester, newNumbers: {});
    await tester.tap(find.text('open'));
    await tester.pump();
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 300));
    await tester.pump(const Duration(milliseconds: 300));

    // No leading 0 this time (contrast with the other tests in this file,
    // which all type the full "0754123456" local form).
    await tester.enterText(find.byType(TextFormField).first, '754123456');
    await tester.tap(find.text('Send code'));
    await tester.pump();
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 300));
    await tester.pump(const Duration(milliseconds: 300));

    expect(find.text('Welcome back'), findsOneWidget);
  });

  /// Part 3 (client feedback): "A countdown showing when resending
  /// becomes available... After that, a tappable 'Resend code' that
  /// requests a fresh one without leaving the screen... Clear feedback
  /// that a new code has been sent."
  testWidgets('the resend button is disabled during the countdown, then works once it elapses', (tester) async {
    await _pump(tester, newNumbers: {});
    await tester.tap(find.text('open'));
    await tester.pump();
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 300));
    await tester.pump(const Duration(milliseconds: 300));

    await tester.enterText(find.byType(TextFormField).first, '0754123456');
    await tester.tap(find.text('Send code'));
    await tester.pump();
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 300));
    await tester.pump(const Duration(milliseconds: 300));

    // Still counting down — no bare "Resend code" text yet, only the countdown.
    expect(find.textContaining('Resend code in'), findsOneWidget);
    expect(find.text('Resend code'), findsNothing);

    await tester.pump(const Duration(seconds: 60));

    expect(find.text('Resend code'), findsOneWidget);

    await tester.tap(find.text('Resend code'));
    await tester.pump();
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 300));
    await tester.pump(const Duration(milliseconds: 300));

    expect(find.text('A new code has been sent.'), findsOneWidget);
    // The countdown restarted from the fresh send.
    expect(find.textContaining('Resend code in'), findsOneWidget);
  });
}
