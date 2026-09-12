import 'package:dio/dio.dart';
import 'package:flutter/widgets.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:sokoni/data/api/catalog_api.dart';
import 'package:sokoni/data/models/paginated_result.dart';
import 'package:sokoni/data/models/product.dart';
import 'package:sokoni/data/repositories/product_repository.dart';
import 'package:sokoni/features/discovery/providers/discovery_providers.dart';
import 'package:sokoni/features/product/providers/search_providers.dart';

/// C2 (client feedback): "the website has subcategories, the app doesn't
/// show them... in search filters, matching the website's behaviour
/// against the same endpoint." Search never sent a category filter of any
/// kind before this. Covers the actual param wiring rather than the whole
/// screen's UI, same reasoning `seller_providers_test.dart` gives for its
/// own scripted-repository doubles.
class _CapturingProductRepository extends ProductRepository {
  _CapturingProductRepository() : super(api: CatalogApi(Dio()), cache: null, dio: Dio());

  int? capturedCategoryId;
  String? capturedQuery;
  int calls = 0;

  @override
  Future<PaginatedResult<Product>> products({
    double? lat,
    double? lng,
    double? radiusKm,
    int? categoryId,
    int? sellerId,
    String? query,
    String sort = 'nearby',
    int page = 1,
    String? make,
    String? model,
    String? year,
  }) async {
    calls++;
    capturedCategoryId = categoryId;
    capturedQuery = query;
    return const PaginatedResult(items: [], currentPage: 1, lastPage: 1, total: 0);
  }
}

Future<WidgetRef> _pumpAndCaptureRef(WidgetTester tester, ProductRepository repo) async {
  late WidgetRef capturedRef;
  await tester.pumpWidget(
    ProviderScope(
      overrides: [productRepositoryProvider.overrideWithValue(repo)],
      child: Consumer(
        builder: (context, ref, _) {
          capturedRef = ref;
          return const SizedBox.shrink();
        },
      ),
    ),
  );
  return capturedRef;
}

void main() {
  group('SearchResultsController', () {
    testWidgets('a plain search sends no category filter', (tester) async {
      final repo = _CapturingProductRepository();
      final ref = await _pumpAndCaptureRef(tester, repo);

      await ref.read(searchResultsProvider.notifier).search('phone');

      expect(repo.capturedQuery, 'phone');
      expect(repo.capturedCategoryId, isNull);
    });

    testWidgets('a subcategory filter takes priority over the parent category', (tester) async {
      final repo = _CapturingProductRepository();
      final ref = await _pumpAndCaptureRef(tester, repo);
      ref.read(searchCategoryIdProvider.notifier).state = 1;
      ref.read(searchSubcategoryIdProvider.notifier).state = 11;

      await ref.read(searchResultsProvider.notifier).search('phone');

      expect(repo.capturedCategoryId, 11);
    });

    testWidgets('with no subcategory picked, the parent category id is sent', (tester) async {
      final repo = _CapturingProductRepository();
      final ref = await _pumpAndCaptureRef(tester, repo);
      ref.read(searchCategoryIdProvider.notifier).state = 1;

      await ref.read(searchResultsProvider.notifier).search('phone');

      expect(repo.capturedCategoryId, 1);
    });

    testWidgets(
      'applyFilters() re-runs the last search term with the current filter, without needing new text',
      (tester) async {
        final repo = _CapturingProductRepository();
        final ref = await _pumpAndCaptureRef(tester, repo);

        await ref.read(searchResultsProvider.notifier).search('phone');
        expect(repo.calls, 1);
        expect(repo.capturedCategoryId, isNull);

        ref.read(searchCategoryIdProvider.notifier).state = 4;
        await ref.read(searchResultsProvider.notifier).applyFilters();

        expect(repo.calls, 2);
        expect(repo.capturedQuery, 'phone');
        expect(repo.capturedCategoryId, 4);
      },
    );
  });
}
