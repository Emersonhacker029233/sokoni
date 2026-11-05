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
import 'package:sokoni/core/push/push_service.dart';
import 'package:sokoni/core/router/routes.dart';
import 'package:sokoni/core/storage/secure_storage.dart';
import 'package:sokoni/data/api/device_api.dart';
import 'package:sokoni/features/auth/presentation/auth_entry_sheet.dart';
import 'package:sokoni/features/legal/presentation/terms_acceptance_screen.dart';

/// Keeps the session in memory: the real storage talks to the keychain
/// through a platform channel this test environment doesn't have.
class _MemoryStorage extends SokoniSecureStorage {
  final _accounts = <StoredAccount>[];
  final _deviceTokens = <String, String>{};

  @override
  Future<String?> readToken() async => _accounts.lastOrNull?.token;

  @override
  Future<int?> readUserId() async => _accounts.lastOrNull?.userId;

  @override
  Future<List<StoredAccount>> readAccounts() async => List.of(_accounts);

  @override
  Future<StoredAccount?> activeAccount() async => _accounts.lastOrNull;

  @override
  Future<void> addOrUpdateAccount(StoredAccount account) async {
    _accounts
      ..removeWhere((a) => a.userId == account.userId)
      ..add(account);
  }

  @override
  Future<String?> readDeviceToken(String login) async => _deviceTokens[login];

  @override
  Future<void> writeDeviceToken(String login, String deviceToken) async => _deviceTokens[login] = deviceToken;
}

class _NoPush extends PushService {
  _NoPush() : super(api: DeviceApi(Dio()));

  @override
  Future<void> registerDevice() async {}
}

/// The App Store Connect demo credentials, exactly as App Review was given
/// them: username `appreview` (or the phone 783 029 233), password
/// `Review2026`, and the fixed code `123456` wherever a code is asked for.
const _reviewPhone = '+255783029233';
const _reviewUsername = 'appreview';
const _reviewPassword = 'Review2026';
const _reviewCode = '123456';

/// The production API as the App Review account meets it: the phone or
/// username + password sign in straight away (no SMS step, `ReviewAccount`
/// server side), answering with whatever Terms version the account has on
/// file. The fixed code works on both code screens (`PhoneOtpService`).
///
/// [asksForCode] plays a server whose `.env` lacks `REVIEW_ACCOUNT_PASSWORD`:
/// the password is right but a code step follows, as for anyone else.
class _ReviewApi implements HttpClientAdapter {
  _ReviewApi({required this.termsVersionOnFile, this.asksForCode = false});

  String termsVersionOnFile;
  final bool asksForCode;
  final requests = <String, Map<String, dynamic>>{};

  bool _isReviewLogin(Object? login) {
    if (login is! String) return false;
    return login.replaceAll(' ', '').endsWith('783029233') || login.trim().toLowerCase() == _reviewUsername;
  }

  /// The server stores the version the app sends with a sign-in (TermsAcceptance).
  void _recordTerms(Map<String, dynamic> body) {
    if (body['terms_version'] is String) termsVersionOnFile = body['terms_version'] as String;
  }

  Map<String, dynamic> _signedIn() => {'token': 'review-token', 'user': _user()};

  String _inFiveMinutes() => DateTime.now().add(const Duration(minutes: 5)).toUtc().toIso8601String();

  Map<String, dynamic> _user() => {
    'id': 42,
    'name': 'App Review',
    'phone': _reviewPhone,
    'username': _reviewUsername,
    'is_seller': false,
    'terms_accepted': true,
    'terms_version': termsVersionOnFile,
  };

  @override
  Future<ResponseBody> fetch(RequestOptions options, Stream<Uint8List>? requestStream, Future<void>? cancelFuture) async {
    final body = options.data is Map ? Map<String, dynamic>.from(options.data as Map) : <String, dynamic>{};
    requests[options.path] = body;

    if (options.path.endsWith('/auth/login')) {
      final correct = body['password'] == _reviewPassword && _isReviewLogin(body['login']);
      if (!correct) return _json({'message': 'Invalid username/phone or password.'}, 422);
      if (asksForCode) return _json({'requires_code': true, 'expires_at': _inFiveMinutes()});
      _recordTerms(body);
      return _json(_signedIn());
    }
    if (options.path.endsWith('/auth/login/verify')) {
      if (!_isReviewLogin(body['login']) || body['code'] != _reviewCode) {
        return _json({'message': 'Invalid or expired code.'}, 422);
      }
      _recordTerms(body);
      return _json({..._signedIn(), 'device_token': 'review-device'});
    }
    if (options.path.endsWith('/auth/otp/request')) {
      if (body['phone'] != _reviewPhone) throw StateError('unexpected phone ${body['phone']}');
      return _json({'is_new_account': false, 'expires_at': _inFiveMinutes()});
    }
    if (options.path.endsWith('/auth/otp/verify')) {
      if (body['phone'] != _reviewPhone || body['code'] != _reviewCode) {
        return _json({'message': 'Invalid or expired code.'}, 422);
      }
      _recordTerms(body);
      return _json(_signedIn());
    }
    if (options.path.endsWith('/auth/terms/accept')) {
      termsVersionOnFile = body['version'] as String;
      return _json({'data': _user()});
    }
    if (options.path.endsWith('/auth/me')) return _json({'data': _user()});
    throw DioException.connectionError(requestOptions: options, reason: 'unscripted path: ${options.path}');
  }

  ResponseBody _json(Map<String, dynamic> body, [int status = 200]) => ResponseBody.fromString(
    jsonEncode(body),
    status,
    headers: {
      Headers.contentTypeHeader: [Headers.jsonContentType],
    },
  );

  @override
  void close({bool force = false}) {}
}

Future<ProviderContainer> _pump(WidgetTester tester, _ReviewApi api) async {
  final router = GoRouter(
    initialLocation: '/',
    routes: [
      GoRoute(
        path: '/',
        builder: (context, state) => Scaffold(
          body: Center(child: ElevatedButton(onPressed: () => showAuthEntrySheet(context), child: const Text('open'))),
        ),
      ),
      GoRoute(path: SokoniRoutes.termsAcceptance, builder: (context, state) => const TermsAcceptanceScreen()),
    ],
  );

  await tester.pumpWidget(
    ProviderScope(
      overrides: [
        secureStorageProvider.overrideWithValue(_MemoryStorage()),
        pushServiceProvider.overrideWithValue(_NoPush()),
        dioProvider.overrideWith((ref) {
          final dio = buildDioClient(storage: ref.watch(secureStorageProvider), onUnauthenticated: () async {});
          dio.httpClientAdapter = api;
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

  return ProviderScope.containerOf(tester.element(find.text('open')));
}

/// Open the sheet: the Terms come first, with one Agree button and no way
/// past them without it. Agree, then choose "Sign in".
Future<void> _agreeAndChooseSignIn(WidgetTester tester) async {
  await tester.tap(find.text('open'));
  await tester.pumpAndSettle();

  expect(find.text('Sokoni Terms of Use'), findsOneWidget);
  expect(find.text('Sign in'), findsNothing, reason: 'no way to sign in before agreeing');
  await tester.ensureVisible(find.text('Agree and continue'));
  await tester.tap(find.text('Agree and continue'));
  await tester.pumpAndSettle();

  // The iOS 1.0 (9) build defines no Google or Apple credentials, exactly
  // like build 7, so neither button exists (auth_entry_sheet_test.dart).
  expect(find.textContaining('Google'), findsNothing);
  expect(find.textContaining('Apple'), findsNothing);

  await tester.tap(find.text('Sign in'));
  await tester.pumpAndSettle();
}

/// Open the sheet, agree once, choose "Sign in", type the App Store Connect
/// demo credentials exactly as a reviewer would, and submit.
Future<void> _signInAsReviewer(WidgetTester tester, {String login = '783 029 233'}) async {
  await _agreeAndChooseSignIn(tester);

  await tester.enterText(find.widgetWithText(TextFormField, 'Username or phone number'), login);
  await tester.enterText(find.widgetWithText(TextFormField, 'Password'), _reviewPassword);
  await tester.tap(find.widgetWithText(FilledButton, 'Sign in'));
  await tester.pumpAndSettle();
}

/// A code screen keeps a one-second resend countdown running, so it never
/// "settles": pump through the sheet animation by hand instead.
Future<void> _pumpCodeStep(WidgetTester tester) async {
  await tester.pump();
  await tester.pump();
  await tester.pump(const Duration(milliseconds: 300));
  await tester.pump(const Duration(milliseconds: 300));
}

/// After a successful sign-in the sheet closes and the countdown with it.
void _expectSignedInWithNothingElseToAgree(WidgetTester tester, ProviderContainer container) {
  expect(container.read(authStateProvider).isAuthenticated, isTrue);
  expect(find.byType(TermsAcceptanceScreen), findsNothing, reason: 'already agreed on the sign-in sheet');
  expect(find.byType(TextFormField), findsNothing, reason: 'the sign-in sheet closed');
  expect(find.text('open'), findsOneWidget);
}

void main() {
  /// Apple Guideline 1.2 + the 2.1 review-account fix: after the Terms
  /// step, +255 783 029 233 / Review2026 still signs in on the first
  /// sign-in screen, with no SMS step, and the agreement to Terms 1.1 goes
  /// to the server with that same request.
  testWidgets('the App Review account agrees once and signs in on the first screen', (tester) async {
    final api = _ReviewApi(termsVersionOnFile: '1.0');
    final container = await _pump(tester, api);

    await _signInAsReviewer(tester);

    final login = api.requests.entries.firstWhere((e) => e.key.endsWith('/auth/login')).value;
    expect(login['login'], '783 029 233');
    expect(login['password'], 'Review2026');
    expect(login['terms_version'], '1.1');
    expect(api.termsVersionOnFile, '1.1');

    _expectSignedInWithNothingElseToAgree(tester, container);
  });

  /// The App Store Connect username is `appreview`: same first screen, same
  /// single request, no code step.
  testWidgets('the App Review username "appreview" signs in on the first screen too', (tester) async {
    final api = _ReviewApi(termsVersionOnFile: '1.0');
    final container = await _pump(tester, api);

    await _signInAsReviewer(tester, login: _reviewUsername);

    final login = api.requests.entries.firstWhere((e) => e.key.endsWith('/auth/login')).value;
    expect(login['login'], _reviewUsername);
    expect(login['password'], _reviewPassword);
    expect(login['terms_version'], '1.1');
    expect(api.requests.keys.where((path) => path.endsWith('/auth/login/verify')), isEmpty, reason: 'no code step');
    expect(api.termsVersionOnFile, '1.1');

    _expectSignedInWithNothingElseToAgree(tester, container);
  });

  /// If the production `.env` were missing REVIEW_ACCOUNT_PASSWORD, the
  /// right password leads to a code step instead of straight in. The
  /// reviewer is still never stuck: the fixed code 123456 finishes it.
  testWidgets('if a code is asked for after the password, 123456 signs the reviewer in', (tester) async {
    final api = _ReviewApi(termsVersionOnFile: '1.0', asksForCode: true);
    final container = await _pump(tester, api);

    await _agreeAndChooseSignIn(tester);
    await tester.enterText(find.widgetWithText(TextFormField, 'Username or phone number'), _reviewUsername);
    await tester.enterText(find.widgetWithText(TextFormField, 'Password'), _reviewPassword);
    await tester.tap(find.widgetWithText(FilledButton, 'Sign in'));
    await _pumpCodeStep(tester);

    expect(find.text('Enter the code we just sent you by SMS.'), findsOneWidget);
    await tester.enterText(find.widgetWithText(TextFormField, 'Verification code'), _reviewCode);
    await tester.tap(find.widgetWithText(FilledButton, 'Verify'));
    await tester.pumpAndSettle();

    final verify = api.requests.entries.firstWhere((e) => e.key.endsWith('/auth/login/verify')).value;
    expect(verify['login'], _reviewUsername);
    expect(verify['code'], _reviewCode);
    expect(verify['terms_version'], '1.1');

    _expectSignedInWithNothingElseToAgree(tester, container);
  });

  /// The other route in the App Review notes: "Sign in with phone number
  /// instead", 783 029 233, Send code, 123456. The Terms agreed to on the
  /// first sheet travel with that sign-in too.
  testWidgets('"Sign in with phone number instead" with the fixed code 123456 signs the reviewer in', (tester) async {
    final api = _ReviewApi(termsVersionOnFile: '1.0');
    final container = await _pump(tester, api);

    await _agreeAndChooseSignIn(tester);
    await tester.tap(find.text('Sign in with phone number instead'));
    await tester.pumpAndSettle();

    await tester.enterText(find.widgetWithText(TextFormField, 'Phone number'), '783 029 233');
    await tester.tap(find.text('Send code'));
    await _pumpCodeStep(tester);

    await tester.enterText(find.widgetWithText(TextFormField, 'Verification code'), _reviewCode);
    await tester.tap(find.widgetWithText(FilledButton, 'Verify'));
    await tester.pumpAndSettle();

    final verify = api.requests.entries.firstWhere((e) => e.key.endsWith('/auth/otp/verify')).value;
    expect(verify['phone'], _reviewPhone);
    expect(verify['code'], _reviewCode);
    expect(verify['terms_version'], '1.1');
    expect(api.termsVersionOnFile, '1.1');

    _expectSignedInWithNothingElseToAgree(tester, container);
  });

  /// A server that still has an older version on file (for example one
  /// not yet updated) gets the one-tap acceptance screen right after
  /// sign-in, never a dead end.
  testWidgets('an account still on Terms 1.0 after sign-in agrees with one tap and continues', (tester) async {
    final api = _ReviewApi(termsVersionOnFile: '1.0');
    // Simulates the server ignoring terms_version (an API from before 1.0 (9)).
    final staleApi = _StaleTermsApi(api);
    await _pumpStale(tester, staleApi);

    await _signInAsReviewer(tester);

    expect(find.byType(TermsAcceptanceScreen), findsOneWidget);
    expect(find.text('Our Terms have changed'), findsOneWidget);
    expect(find.byType(Checkbox), findsNothing);

    await tester.ensureVisible(find.text('Agree and continue'));
    await tester.tap(find.text('Agree and continue'));
    await tester.pumpAndSettle();

    expect(api.requests.entries.firstWhere((e) => e.key.endsWith('/auth/terms/accept')).value['version'], '1.1');
    expect(find.byType(TermsAcceptanceScreen), findsNothing);
    expect(find.text('open'), findsOneWidget);
  });
}

/// Wraps [_ReviewApi] but drops `terms_version` from sign-in, like an API
/// deployed before this build.
class _StaleTermsApi extends _ReviewApi {
  _StaleTermsApi(this.inner) : super(termsVersionOnFile: inner.termsVersionOnFile);

  final _ReviewApi inner;

  @override
  Future<ResponseBody> fetch(RequestOptions options, Stream<Uint8List>? requestStream, Future<void>? cancelFuture) {
    if (options.path.endsWith('/auth/login') && options.data is Map) {
      options.data = Map<String, dynamic>.from(options.data as Map)..remove('terms_version');
    }
    return inner.fetch(options, requestStream, cancelFuture);
  }
}

Future<void> _pumpStale(WidgetTester tester, _StaleTermsApi api) => _pump(tester, api);
