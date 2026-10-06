import 'dart:typed_data';

import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:sokoni/core/network/api_exception.dart';
import 'package:sokoni/core/storage/secure_storage.dart';
import 'package:sokoni/data/api/auth_api.dart';
import 'package:sokoni/data/repositories/auth_repository.dart';

/// Apple Guideline 5.1.1(v): account creation happens in-app, so deletion
/// must too. Unlike `logout()` (which clears the local session
/// regardless of whether the server call succeeds — a deliberate
/// best-effort design, see its own docblock),
/// `AuthRepository.deleteAccount()` must never clear the local session on
/// a failed server call, since that would tell the user their account is
/// gone when it still exists.
class _ScriptedAdapter implements HttpClientAdapter {
  _ScriptedAdapter(this.statusCode);

  final int statusCode;

  @override
  Future<ResponseBody> fetch(RequestOptions options, Stream<Uint8List>? requestStream, Future<void>? cancelFuture) async {
    if (options.path.endsWith('/auth/me') && options.method == 'DELETE') {
      return ResponseBody.fromString('{}', statusCode);
    }
    throw DioException.connectionError(requestOptions: options, reason: 'unscripted path: ${options.method} ${options.path}');
  }

  @override
  void close({bool force = false}) {}
}

class _TrackingSecureStorage extends SokoniSecureStorage {
  bool clearSessionCalled = false;

  @override
  Future<String?> readToken() async => 'a-real-token';

  @override
  Future<void> clearSession() async {
    clearSessionCalled = true;
  }
}

AuthRepository _buildRepository({required int statusCode, required _TrackingSecureStorage storage}) {
  final dio = Dio(BaseOptions(baseUrl: 'https://api.sokoni.co.tz'));
  dio.httpClientAdapter = _ScriptedAdapter(statusCode);

  return AuthRepository(api: AuthApi(dio), storage: storage, dio: dio);
}

void main() {
  test('a successful deletion clears the local session', () async {
    final storage = _TrackingSecureStorage();
    final repo = _buildRepository(statusCode: 200, storage: storage);

    await repo.deleteAccount();

    expect(storage.clearSessionCalled, isTrue);
  });

  test('a failed deletion throws and does NOT clear the local session', () async {
    final storage = _TrackingSecureStorage();
    final repo = _buildRepository(statusCode: 500, storage: storage);

    await expectLater(repo.deleteAccount(), throwsA(isA<ApiException>()));

    expect(
      storage.clearSessionCalled,
      isFalse,
      reason: 'a failed server-side deletion must not look like a successful sign-out locally',
    );
  });
}
