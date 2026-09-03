import 'dart:convert';
import 'dart:typed_data';

import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:sokoni/core/network/dio_client.dart';
import 'package:sokoni/core/storage/secure_storage.dart';

class _ScriptedSecureStorage extends SokoniSecureStorage {
  _ScriptedSecureStorage({this.token});

  String? token;
  int clearSessionCalls = 0;

  @override
  Future<String?> readToken() async => token;

  @override
  Future<void> clearSession() async {
    clearSessionCalls++;
    token = null;
  }
}

class _RespondsWith401Adapter implements HttpClientAdapter {
  @override
  Future<ResponseBody> fetch(RequestOptions options, Stream<Uint8List>? requestStream, Future<void>? cancelFuture) async {
    return ResponseBody.fromString(
      jsonEncode({'message': 'Unauthenticated.'}),
      401,
      headers: {
        Headers.contentTypeHeader: [Headers.jsonContentType],
      },
    );
  }

  @override
  void close({bool force = false}) {}
}

void main() {
  test(
    'a 401 on a request that DID carry a token clears the session — the server actually rejected a real token',
    () async {
      final storage = _ScriptedSecureStorage(token: 'a-real-token');
      var onUnauthenticatedCalled = 0;

      final dio = buildDioClient(
        storage: storage,
        onUnauthenticated: () async => onUnauthenticatedCalled++,
      );
      dio.httpClientAdapter = _RespondsWith401Adapter();

      await expectLater(dio.get<void>('/anything'), throwsA(isA<DioException>()));

      expect(storage.clearSessionCalls, 1);
      expect(onUnauthenticatedCalled, 1);
    },
  );

  test(
    'a 401 on a request that carried NO token (a purely local read failure/race, not a server rejection) '
    'must never wipe the stored session — regression for the bug where a transient secure-storage hiccup '
    'permanently destroyed a session that was never actually rejected by anything',
    () async {
      final storage = _ScriptedSecureStorage(token: null);
      var onUnauthenticatedCalled = 0;

      final dio = buildDioClient(
        storage: storage,
        onUnauthenticated: () async => onUnauthenticatedCalled++,
      );
      dio.httpClientAdapter = _RespondsWith401Adapter();

      await expectLater(dio.get<void>('/anything'), throwsA(isA<DioException>()));

      expect(storage.clearSessionCalls, 0);
      expect(onUnauthenticatedCalled, 0);
    },
  );
}
