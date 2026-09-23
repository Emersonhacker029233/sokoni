import 'dart:async';
import 'dart:convert';
import 'dart:typed_data';

import 'package:dio/dio.dart';
import 'package:fake_async/fake_async.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:sokoni/core/network/api_exception.dart';
import 'package:sokoni/data/api/catalog_api.dart';
import 'package:sokoni/data/repositories/product_repository.dart';

/// Bug (client feedback): "Unexpected error: type `List<dynamic>?` is not
/// a subtype of type `Map<String, dynamic>?`" — a JSON field the client
/// expects as an object came back as an empty array (fixed server-side:
/// see api/app/Http/Resources/ProductResource.php). Fixed at the source,
/// but the client must still never let a single malformed response
/// propagate a raw, untyped exception and take the rest of the screen
/// down with it — every repository call is meant to surface a typed
/// [ApiException] a screen's own `.when(error: ...)` can render as a
/// retryable message, same as a network failure would.
class _ScriptedAdapter implements HttpClientAdapter {
  _ScriptedAdapter(this.body, this.statusCode);

  final Map<String, dynamic> body;
  final int statusCode;

  @override
  Future<ResponseBody> fetch(RequestOptions options, Stream<Uint8List>? requestStream, Future<void>? cancelFuture) async {
    return ResponseBody.fromString(
      jsonEncode(body),
      statusCode,
      headers: {
        Headers.contentTypeHeader: [Headers.jsonContentType],
      },
    );
  }

  @override
  void close({bool force = false}) {}
}

/// Never resolves — simulates a genuinely stuck connection, so the
/// timeout added below (rather than a fast rejection) is what's actually
/// exercised.
class _HangingAdapter implements HttpClientAdapter {
  @override
  Future<ResponseBody> fetch(RequestOptions options, Stream<Uint8List>? requestStream, Future<void>? cancelFuture) {
    return Completer<ResponseBody>().future;
  }

  @override
  void close({bool force = false}) {}
}

Map<String, dynamic> _productJson({required dynamic attributes}) => {
  'id': 1,
  'title': 'A phone',
  'price': 100000,
  'currency': 'TZS',
  'stock': 1,
  'condition': 'new',
  'views': 0,
  'is_active': true,
  'is_hidden': false,
  'is_sponsored': false,
  'media': [],
  'attributes': attributes,
  'created_at': '2026-01-01T00:00:00.000000Z',
};

ProductRepository _repositoryReturning(Map<String, dynamic> body, {int statusCode = 200}) {
  final dio = Dio(BaseOptions(baseUrl: 'https://example.test'));
  dio.httpClientAdapter = _ScriptedAdapter(body, statusCode);
  return ProductRepository(api: CatalogApi(dio), cache: null, dio: dio);
}

void main() {
  test('a well-formed product (empty object attributes) parses normally', () async {
    final repo = _repositoryReturning({'data': _productJson(attributes: <String, dynamic>{})});

    final product = await repo.product(1);

    expect(product.id, 1);
    expect(product.attributes, isEmpty);
  });

  test(
    'a malformed product (attributes sent as a bare list, the actual bug) '
    'surfaces as a typed ApiException, never a raw uncaught exception',
    () async {
      final repo = _repositoryReturning({'data': _productJson(attributes: <dynamic>[])});

      // The whole point: whatever this throws must be an ApiException a
      // screen's error handler can catch and render — not a bare
      // TypeError propagating past every catch clause in the app.
      await expectLater(repo.product(1), throwsA(isA<ApiException>()));
    },
  );

  test(
    'the product LIST endpoint (the home feed\'s own data source) does the same — '
    'this repository used to rethrow a malformed-response failure as the raw, '
    'untyped exception instead of the typed one every other failure gets',
    () async {
      final repo = _repositoryReturning({
        'data': [_productJson(attributes: <dynamic>[])],
        'meta': {'current_page': 1, 'last_page': 1, 'total': 1},
      });

      await expectLater(repo.products(), throwsA(isA<ApiException>()));
    },
  );

  test(
    'Part 1 (client feedback), "a screen must never load indefinitely": a '
    'genuinely stuck product-detail request times out into a retryable '
    'ApiException rather than staying pending forever',
    () {
      fakeAsync((async) {
        final dio = Dio(BaseOptions(baseUrl: 'https://example.test'))..httpClientAdapter = _HangingAdapter();
        final repo = ProductRepository(api: CatalogApi(dio), cache: null, dio: dio);

        Object? caught;
        // ignore: unawaited_futures
        repo.product(1).then((_) {}, onError: (Object e) {
          caught = e;
        });

        // Well past the 20s ceiling ProductRepository.product() enforces —
        // proves this resolves on its own rather than staying pending
        // for as long as Dio's own much longer global timeout allows.
        async.elapse(const Duration(seconds: 25));

        expect(caught, isA<RequestTimeoutException>());
      });
    },
  );
}
