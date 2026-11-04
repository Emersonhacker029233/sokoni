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
import 'package:sokoni/core/storage/secure_storage.dart';
import 'package:sokoni/data/models/category.dart';
import 'package:sokoni/data/models/feed_item.dart';
import 'package:sokoni/data/models/product.dart';
import 'package:sokoni/data/models/seller_summary.dart';
import 'package:sokoni/features/moderation/presentation/blocked_accounts_screen.dart';
import 'package:sokoni/shared/widgets/feed_card.dart';

class _SignedIn extends AuthStateController {
  @override
  AuthState build() => const AuthState(AuthStatus.authenticated);
}

class _TokenStorage extends SokoniSecureStorage {
  @override
  Future<String?> readToken() async => 'buyer-token';
}

/// The moderation endpoints as the API answers them, recording what the
/// app sends.
class _ModerationApi implements HttpClientAdapter {
  final posts = <String, Map<String, dynamic>>{};
  final deletes = <String>[];
  var blocked = <Map<String, dynamic>>[];

  @override
  Future<ResponseBody> fetch(RequestOptions options, Stream<Uint8List>? requestStream, Future<void>? cancelFuture) async {
    final path = options.path;
    if (options.method == 'POST') posts[path] = Map<String, dynamic>.from(options.data as Map);

    if (path.endsWith('/auth/me')) {
      return _json({
        'data': {'id': 5, 'name': 'Buyer', 'is_seller': false, 'terms_accepted': true, 'terms_version': '1.1'},
      });
    }
    if (path.endsWith('/blocks') && options.method == 'GET') return _json({'data': blocked});
    if (path.endsWith('/blocks') && options.method == 'POST') {
      final row = {'user_id': 9, 'name': 'Amina', 'seller_id': 1, 'shop_name': 'Amina Electronics', 'handle': 'amina'};
      blocked = [row];
      return _json({'data': row}, 201);
    }
    if (path.contains('/blocks/') && options.method == 'DELETE') {
      deletes.add(path);
      blocked = [];
      return _json({'message': 'Unblocked.'});
    }
    if (path.endsWith('/reports')) return _json({'message': 'Report submitted for review.'}, 201);
    throw DioException.connectionError(requestOptions: options, reason: 'unscripted: ${options.method} $path');
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

const _product = Product(
  id: 1,
  title: 'Samsung Galaxy A54',
  price: 850000,
  currency: 'TZS',
  category: SokoniCategory(id: 1, nameEn: 'Phones', nameSw: 'Simu'),
  seller: SellerSummary(id: 1, shopName: 'Amina Electronics', handle: 'amina', isVerified: true),
);

Future<void> _pump(WidgetTester tester, _ModerationApi api, {Widget? home}) async {
  final router = GoRouter(
    initialLocation: '/',
    routes: [
      GoRoute(
        path: '/',
        builder: (context, state) =>
            home ??
            Scaffold(
              body: ListView(children: const [FeedCard(item: FeedItem.product(_product))]),
            ),
      ),
    ],
  );

  await tester.pumpWidget(
    ProviderScope(
      overrides: [
        authStateProvider.overrideWith(_SignedIn.new),
        secureStorageProvider.overrideWithValue(_TokenStorage()),
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
  await tester.pumpAndSettle();
}

void main() {
  /// Apple Guideline 1.2: "a mechanism for users to flag objectionable
  /// content" — with reasons that name it, sent as codes the server knows.
  testWidgets('reporting a listing offers objectionable-content reasons and sends a code', (tester) async {
    final api = _ModerationApi();
    await _pump(tester, api);

    await tester.tap(find.byTooltip('Report or block'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Report this listing'));
    await tester.pumpAndSettle();

    expect(find.text('Our team reviews every report within 24 hours.'), findsOneWidget);
    for (final reason in ['Offensive or abusive', 'Harassment or hate', 'Sexual or violent content', 'Scam or fraud']) {
      expect(find.text(reason), findsOneWidget);
    }

    await tester.tap(find.text('Offensive or abusive'));
    await tester.pumpAndSettle();

    expect(api.posts.entries.firstWhere((e) => e.key.endsWith('/reports')).value, {
      'reportable_type': 'product',
      'reportable_id': 1,
      'reason': 'abusive',
    });
    expect(find.text("Thanks — we'll take a look."), findsOneWidget);
  });

  /// Apple Guideline 1.2: blocking "should remove it from the user's feed
  /// instantly" — the card is gone as soon as the block is confirmed.
  testWidgets('blocking a shop from its card removes the card at once', (tester) async {
    final api = _ModerationApi();
    await _pump(tester, api);
    expect(find.text('Amina Electronics'), findsOneWidget);

    await tester.tap(find.byTooltip('Report or block'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Block Amina Electronics'));
    await tester.pumpAndSettle();

    expect(find.text('Block Amina Electronics?'), findsOneWidget);
    expect(find.textContaining('Our team is notified'), findsOneWidget);
    await tester.tap(find.widgetWithText(FilledButton, 'Block'));
    await tester.pumpAndSettle();

    expect(api.posts.entries.firstWhere((e) => e.key.endsWith('/blocks')).value, {'seller_id': 1});
    expect(find.text('Samsung Galaxy A54', findRichText: true), findsNothing);
    expect(find.text('Amina Electronics is blocked.'), findsOneWidget);
  });

  testWidgets('Blocked accounts lists them and Unblock removes the block', (tester) async {
    final api = _ModerationApi()
      ..blocked = [
        {'user_id': 9, 'name': 'Amina', 'seller_id': 1, 'shop_name': 'Amina Electronics', 'handle': 'amina'},
      ];
    await _pump(tester, api, home: const BlockedAccountsScreen());

    expect(find.text('Amina Electronics'), findsOneWidget);
    expect(find.text('@amina'), findsOneWidget);

    await tester.tap(find.text('Unblock'));
    await tester.pumpAndSettle();

    expect(api.deletes.single, endsWith('/blocks/9'));
    expect(find.text('Amina Electronics'), findsNothing);
    expect(find.textContaining("You haven't blocked anyone"), findsOneWidget);
  });
}
