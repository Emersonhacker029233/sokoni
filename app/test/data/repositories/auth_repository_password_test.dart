import 'dart:convert';
import 'dart:typed_data';

import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:sokoni/core/storage/secure_storage.dart';
import 'package:sokoni/data/api/auth_api.dart';
import 'package:sokoni/data/repositories/auth_repository.dart';

/// Username/password rework (CLAUDE.md Part D) — the client-side half of
/// `PasswordAuthController`'s own feature test. Scripts every response
/// shape the server can send, confirming `AuthRepository` reads them
/// correctly (especially the two-shape `login()` response — a full
/// `AuthResponse` vs. `{requires_code: true}` — and that the device token
/// returned by `verifyLogin()` is actually stored and re-sent on the next
/// `login()` call for that same login string).
class _ScriptedAdapter implements HttpClientAdapter {
  _ScriptedAdapter({required this.responses});

  /// path -> response body (decoded once per call via a function so a
  /// test can change behaviour between calls, e.g. "recognise the device
  /// the second time").
  final Map<String, Map<String, dynamic> Function(Map<String, dynamic> body)> responses;

  final List<Map<String, dynamic>> requestBodies = [];

  @override
  Future<ResponseBody> fetch(RequestOptions options, Stream<Uint8List>? requestStream, Future<void>? cancelFuture) async {
    final body = options.data is Map<String, dynamic> ? options.data as Map<String, dynamic> : <String, dynamic>{};
    requestBodies.add({'path': options.path, ...body});

    final handler = responses[options.path];
    if (handler == null) {
      throw DioException.connectionError(requestOptions: options, reason: 'unscripted path: ${options.path}');
    }

    return ResponseBody.fromString(
      jsonEncode(handler(body)),
      200,
      headers: {Headers.contentTypeHeader: [Headers.jsonContentType]},
    );
  }

  @override
  void close({bool force = false}) {}
}

class _InMemorySecureStorage extends SokoniSecureStorage {
  final Map<String, String> _deviceTokens = {};
  bool addOrUpdateAccountCalled = false;

  @override
  Future<String?> readDeviceToken(String login) async => _deviceTokens[login.toLowerCase()];

  @override
  Future<void> writeDeviceToken(String login, String deviceToken) async {
    _deviceTokens[login.toLowerCase()] = deviceToken;
  }

  @override
  Future<void> addOrUpdateAccount(StoredAccount account) async {
    addOrUpdateAccountCalled = true;
  }
}

Map<String, dynamic> _userJson() => {
  'id': 1,
  'name': 'Amina',
  'is_seller': false,
  'terms_accepted': true,
};

void main() {
  test('login() reads a requires_code response as a code challenge, not a sign-in', () async {
    final adapter = _ScriptedAdapter(
      responses: {
        '/auth/login': (_) => {'requires_code': true, 'expires_at': '2026-01-01T00:05:00.000Z'},
      },
    );
    final dio = Dio()..httpClientAdapter = adapter;
    final storage = _InMemorySecureStorage();
    final repo = AuthRepository(api: AuthApi(dio), storage: storage, dio: dio);

    final challenge = await repo.login(login: 'amina', password: 'correct-password');

    expect(challenge.requiresCode, isTrue);
    expect(challenge.response, isNull);
    expect(storage.addOrUpdateAccountCalled, isFalse, reason: 'no account should be persisted until the code step completes');
  });

  test('login() reads a full AuthResponse (recognised device) as an immediate sign-in', () async {
    final adapter = _ScriptedAdapter(
      responses: {
        '/auth/login': (_) => {'token': 'a-real-token', 'user': _userJson(), 'is_new_account': false},
      },
    );
    final dio = Dio()..httpClientAdapter = adapter;
    final storage = _InMemorySecureStorage();
    final repo = AuthRepository(api: AuthApi(dio), storage: storage, dio: dio);

    final challenge = await repo.login(login: 'amina', password: 'correct-password');

    expect(challenge.requiresCode, isFalse);
    expect(challenge.response?.token, 'a-real-token');
    expect(storage.addOrUpdateAccountCalled, isTrue);
  });

  test('login() sends along a previously stored device token for this exact login string', () async {
    final adapter = _ScriptedAdapter(
      responses: {
        '/auth/login': (_) => {'token': 'a-real-token', 'user': _userJson(), 'is_new_account': false},
      },
    );
    final dio = Dio()..httpClientAdapter = adapter;
    final storage = _InMemorySecureStorage();
    await storage.writeDeviceToken('amina', 'remembered-device-token');
    final repo = AuthRepository(api: AuthApi(dio), storage: storage, dio: dio);

    await repo.login(login: 'amina', password: 'correct-password');

    expect(adapter.requestBodies.single['device_token'], 'remembered-device-token');
  });

  test('verifyLogin() stores the fresh device_token against the login string used', () async {
    final adapter = _ScriptedAdapter(
      responses: {
        '/auth/login/verify': (_) => {
          'token': 'a-real-token',
          'user': _userJson(),
          'is_new_account': false,
          'device_token': 'brand-new-device-token',
        },
      },
    );
    final dio = Dio()..httpClientAdapter = adapter;
    final storage = _InMemorySecureStorage();
    final repo = AuthRepository(api: AuthApi(dio), storage: storage, dio: dio);

    await repo.verifyLogin(login: 'amina', code: '123456');

    expect(await storage.readDeviceToken('amina'), 'brand-new-device-token');
  });

  test('checkUsernameAvailable reads the available flag', () async {
    final adapter = _ScriptedAdapter(responses: {'/auth/username/check': (_) => {'available': false}});
    final dio = Dio()..httpClientAdapter = adapter;
    final repo = AuthRepository(api: AuthApi(dio), storage: _InMemorySecureStorage(), dio: dio);

    expect(await repo.checkUsernameAvailable('admin'), isFalse);
  });

  test('forgotPasswordReset persists the account and stores a device token, same as verifyLogin', () async {
    final adapter = _ScriptedAdapter(
      responses: {
        '/auth/forgot-password/reset': (_) => {
          'token': 'a-real-token',
          'user': _userJson(),
          'is_new_account': false,
          'device_token': 'reset-device-token',
        },
      },
    );
    final dio = Dio()..httpClientAdapter = adapter;
    final storage = _InMemorySecureStorage();
    final repo = AuthRepository(api: AuthApi(dio), storage: storage, dio: dio);

    await repo.forgotPasswordReset(login: 'amina', code: '123456', password: 'brand-new-password');

    expect(storage.addOrUpdateAccountCalled, isTrue);
    expect(await storage.readDeviceToken('amina'), 'reset-device-token');
  });
}
