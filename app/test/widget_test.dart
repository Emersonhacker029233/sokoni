import 'dart:typed_data';

import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:sokoni/core/network/dio_client.dart';
import 'package:sokoni/core/providers.dart';
import 'package:sokoni/main.dart';

/// Fails every request synchronously with a connection error, so
/// [ProductRepository] takes its offline-cache fallback path instead of
/// leaving a real, unmocked HTTP request pending when the test tears down.
class _FailFastAdapter implements HttpClientAdapter {
  @override
  Future<ResponseBody> fetch(
    RequestOptions options,
    Stream<Uint8List>? requestStream,
    Future<void>? cancelFuture,
  ) {
    throw DioException.connectionError(requestOptions: options, reason: 'offline in tests');
  }

  @override
  void close({bool force = false}) {}
}

void main() {
  testWidgets('splash settles into the home tab shell', (WidgetTester tester) async {
    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          dioProvider.overrideWith((ref) {
            final dio = buildDioClient(
              storage: ref.watch(secureStorageProvider),
              onUnauthenticated: () async {},
            );
            dio.httpClientAdapter = _FailFastAdapter();
            return dio;
          }),
        ],
        child: const SokoniApp(),
      ),
    );

    // Splash plays first (max 900ms); pump it to completion explicitly
    // rather than pumpAndSettle — the home tab's shimmer skeleton animates
    // continuously while its network request is pending, so pumpAndSettle
    // would never find a quiet frame even with the fail-fast adapter above.
    await tester.pump(const Duration(milliseconds: 950));
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 100));

    expect(find.text('Sokoni'), findsWidgets);
  });
}
