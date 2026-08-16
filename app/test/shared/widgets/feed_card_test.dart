import 'dart:typed_data';

import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';

import 'package:sokoni/core/l10n/gen/app_localizations.dart';
import 'package:sokoni/core/network/dio_client.dart';
import 'package:sokoni/core/providers.dart';
import 'package:sokoni/data/models/category.dart';
import 'package:sokoni/data/models/feed_item.dart';
import 'package:sokoni/data/models/product.dart';
import 'package:sokoni/data/models/seller_summary.dart';
import 'package:sokoni/shared/widgets/feed_card.dart';

class _FailFastAdapter implements HttpClientAdapter {
  @override
  Future<ResponseBody> fetch(RequestOptions options, Stream<Uint8List>? requestStream, Future<void>? cancelFuture) {
    throw DioException.connectionError(requestOptions: options, reason: 'offline in tests');
  }

  @override
  void close({bool force = false}) {}
}

Product _buildProduct() {
  return const Product(
    id: 1,
    title: 'Samsung Galaxy A54',
    price: 850000,
    currency: 'TZS',
    category: SokoniCategory(id: 1, nameEn: 'Phones', nameSw: 'Simu'),
    seller: SellerSummary(id: 1, shopName: 'Amina Electronics', handle: 'aminaelectronics', isVerified: true),
  );
}

void main() {
  testWidgets('a product feed card renders the seller header, price and caption', (tester) async {
    final router = GoRouter(
      initialLocation: '/',
      routes: [
        GoRoute(
          // FeedCard is designed to sit inside a ListView (see
          // `_ForYouTabBody` in home_screen.dart), which gives each card
          // its natural, unconstrained height — wrapping it directly in a
          // bare Scaffold body here would clip it to the test viewport's
          // fixed height instead, the same test-environment-only overflow
          // ProductCard's own test avoids with a properly-sized parent.
          path: '/',
          builder: (context, state) => Scaffold(
            body: ListView(children: [FeedCard(item: FeedItem.product(_buildProduct()))]),
          ),
        ),
      ],
    );

    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          dioProvider.overrideWith((ref) {
            final dio = buildDioClient(storage: ref.watch(secureStorageProvider), onUnauthenticated: () async {});
            dio.httpClientAdapter = _FailFastAdapter();
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
    await tester.pump();

    expect(find.text('Amina Electronics'), findsOneWidget);
    expect(find.text('TSh 850,000'), findsOneWidget);
    // The caption is a RichText (the "@handle" prefix needs a different
    // font weight from the title within one line) — find.textContaining
    // skips standalone RichText widgets unless told to look inside them.
    expect(find.textContaining('Samsung Galaxy A54', findRichText: true), findsOneWidget);
    expect(find.byIcon(Icons.verified_rounded), findsOneWidget);
  });
}
