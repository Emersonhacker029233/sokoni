import 'dart:typed_data';

import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:sokoni/core/l10n/gen/app_localizations.dart';
import 'package:sokoni/core/network/dio_client.dart';
import 'package:sokoni/core/providers.dart';
import 'package:sokoni/features/auth/presentation/sign_in_prompt_sheet.dart';

class _FailFastAdapter implements HttpClientAdapter {
  @override
  Future<ResponseBody> fetch(RequestOptions options, Stream<Uint8List>? requestStream, Future<void>? cancelFuture) {
    throw DioException.connectionError(requestOptions: options, reason: 'offline in tests');
  }

  @override
  void close({bool force = false}) {}
}

Future<void> _pump(WidgetTester tester, {required String message}) async {
  await tester.pumpWidget(
    ProviderScope(
      overrides: [
        dioProvider.overrideWith((ref) {
          final dio = buildDioClient(storage: ref.watch(secureStorageProvider), onUnauthenticated: () async {});
          dio.httpClientAdapter = _FailFastAdapter();
          return dio;
        }),
      ],
      child: MaterialApp(
        localizationsDelegates: AppLocalizations.localizationsDelegates,
        supportedLocales: AppLocalizations.supportedLocales,
        home: Builder(
          builder: (context) => Scaffold(
            body: Center(
              child: ElevatedButton(
                onPressed: () => showSignInPrompt(context, message: message),
                child: const Text('open'),
              ),
            ),
          ),
        ),
      ),
    ),
  );
}

void main() {
  testWidgets('shows the given invitation message, not a generic error', (tester) async {
    await _pump(tester, message: 'Sign in to save items');
    await tester.tap(find.text('open'));
    await tester.pumpAndSettle();

    expect(find.text('Sign in to save items'), findsOneWidget);
    expect(find.textContaining('error', findRichText: true), findsNothing);
    expect(find.textContaining('failed', findRichText: true), findsNothing);
  });

  testWidgets(
    'tapping "Sign in" closes the prompt and opens the auth entry chooser, '
    'then choosing "Sign in" there opens the actual phone sign-in sheet',
    (tester) async {
      await _pump(tester, message: 'Sign in to save items');
      await tester.tap(find.text('open'));
      await tester.pumpAndSettle();

      await tester.tap(find.text('Sign in'));
      await tester.pumpAndSettle();

      expect(find.text('Sign in to save items'), findsNothing, reason: 'the invitation prompt itself should be gone');
      // The auth entry chooser is now showing — its own "Sign in" option,
      // not the phone form yet.
      expect(find.byType(TextFormField), findsNothing);

      await tester.tap(find.text('Sign in'));
      await tester.pumpAndSettle();

      // The real phone sign-in sheet's phone-number field should now be showing.
      expect(find.byType(TextFormField), findsWidgets);
    },
  );

  testWidgets('dismissing just closes the sheet with no navigation anywhere else', (tester) async {
    await _pump(tester, message: 'Sign in to save items');
    await tester.tap(find.text('open'));
    await tester.pumpAndSettle();

    await tester.ensureVisible(find.text('Not now'));
    await tester.tap(find.text('Not now'));
    await tester.pumpAndSettle();

    expect(find.text('Sign in to save items'), findsNothing);
    expect(find.byType(TextFormField), findsNothing);
    expect(find.text('open'), findsOneWidget, reason: 'back on the original screen');
  });
}
