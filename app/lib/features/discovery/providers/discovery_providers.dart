import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_riverpod/legacy.dart';

import '../../../core/providers.dart';
import '../../../data/api/catalog_api.dart';
import '../../../data/models/category.dart';
import '../../../data/models/product.dart';
import '../../../data/repositories/product_repository.dart';
import 'discovery_location.dart';

final catalogApiProvider = Provider<CatalogApi>((ref) {
  return CatalogApi(ref.watch(dioProvider));
});

final productRepositoryProvider = Provider<ProductRepository>((ref) {
  return ProductRepository(
    api: ref.watch(catalogApiProvider),
    cache: ref.watch(appDatabaseProvider),
    dio: ref.watch(dioProvider),
  );
});

/// The raw flat list the API returns — both top-level categories and every
/// subcategory (mega menu / product-form subcategory picker), undifferentiated.
/// Most call sites want only the top-level ones; use
/// [topLevelCategoriesProvider] for those, and [subcategoriesProvider] for
/// a given parent's children.
final categoriesProvider = FutureProvider<List<SokoniCategory>>((ref) async {
  return ref.watch(productRepositoryProvider).categories();
});

/// Top-level categories only — the home chip row, seller onboarding's shop
/// category, and the "Create an account" seller details step all pick from
/// this, never a subcategory.
final topLevelCategoriesProvider = FutureProvider<List<SokoniCategory>>((ref) async {
  final all = await ref.watch(categoriesProvider.future);

  return all.where((c) => c.parentId == null).toList();
});

/// A given parent's own subcategories, in sort order — the product form's
/// optional "Subcategory" picker.
final subcategoriesProvider = FutureProvider.family<List<SokoniCategory>, int>((ref, parentId) async {
  final all = await ref.watch(categoriesProvider.future);

  return all.where((c) => c.parentId == parentId).toList();
});

/// The signed-in seller's own Listings, unpaginated to a single page — used
/// by the Offer/Showcase/Update composers' "pick one of your own Listings"
/// step (CLAUDE.md Part 3), not the My Shop dashboard's own paginated list.
final myProductsProvider = FutureProvider.autoDispose<List<Product>>((ref) async {
  final result = await ref.watch(productRepositoryProvider).myProducts();
  return result.items;
});

/// Radius presets from CLAUDE.md feature 1 — null means "All" (unbounded).
enum RadiusPreset {
  km1(1),
  km5(5),
  km10(10),
  km25(25),
  all(null);

  const RadiusPreset(this.km);
  final double? km;
}

final radiusPresetProvider = StateProvider<RadiusPreset>((ref) => RadiusPreset.km5);

/// null = "All" category chip selected.
final selectedCategoryIdProvider = StateProvider<int?>((ref) => null);

final searchQueryProvider = StateProvider<String>((ref) => '');

enum FeedSort { nearby, trending, newest }

final feedSortProvider = StateProvider<FeedSort>((ref) => FeedSort.nearby);

/// List ⇄ map toggle (CLAUDE.md feature 1).
final feedIsMapViewProvider = StateProvider<bool>((ref) => false);

class DiscoveryFeedState {
  const DiscoveryFeedState({required this.items, required this.hasMore, required this.page});

  final List<Product> items;
  final bool hasMore;
  final int page;
}

/// Drives the home feed: re-fetches automatically whenever location,
/// radius, category, search or sort change (each read via `ref.watch`),
/// and supports pull-to-refresh / infinite scroll on top of that.
class DiscoveryFeedController extends AsyncNotifier<DiscoveryFeedState> {
  @override
  Future<DiscoveryFeedState> build() async {
    final location = await ref.watch(discoveryLocationProvider.future);
    final result = await ref
        .watch(productRepositoryProvider)
        .products(
          lat: location.coords?.lat,
          lng: location.coords?.lng,
          radiusKm: ref.watch(radiusPresetProvider).km,
          categoryId: ref.watch(selectedCategoryIdProvider),
          query: ref.watch(searchQueryProvider),
          sort: switch (ref.watch(feedSortProvider)) {
            FeedSort.nearby => 'nearby',
            FeedSort.trending => 'trending',
            FeedSort.newest => 'newest',
          },
          page: 1,
        );
    return DiscoveryFeedState(items: result.items, hasMore: result.hasMore, page: result.currentPage);
  }

  Future<void> refresh() async {
    ref.invalidateSelf();
    await future;
  }

  Future<void> loadMore() async {
    final current = state.value;
    if (current == null || !current.hasMore) return;

    final location = await ref.read(discoveryLocationProvider.future);
    final result = await ref
        .read(productRepositoryProvider)
        .products(
          lat: location.coords?.lat,
          lng: location.coords?.lng,
          radiusKm: ref.read(radiusPresetProvider).km,
          categoryId: ref.read(selectedCategoryIdProvider),
          query: ref.read(searchQueryProvider),
          sort: switch (ref.read(feedSortProvider)) {
            FeedSort.nearby => 'nearby',
            FeedSort.trending => 'trending',
            FeedSort.newest => 'newest',
          },
          page: current.page + 1,
        );

    state = AsyncData(
      DiscoveryFeedState(
        items: [...current.items, ...result.items],
        hasMore: result.hasMore,
        page: result.currentPage,
      ),
    );
  }
}

final discoveryFeedProvider = AsyncNotifierProvider<DiscoveryFeedController, DiscoveryFeedState>(
  DiscoveryFeedController.new,
);
