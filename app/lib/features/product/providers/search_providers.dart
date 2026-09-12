import 'dart:convert';

import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_riverpod/legacy.dart';

import '../../../core/providers.dart';
import '../../../data/models/product.dart';
import '../../discovery/providers/discovery_providers.dart';

const _searchHistoryKey = 'search_history';
const _maxHistoryEntries = 10;

class SearchHistoryController extends AsyncNotifier<List<String>> {
  @override
  Future<List<String>> build() async {
    final raw = await ref.watch(appDatabaseProvider).getKeyValue(_searchHistoryKey);
    if (raw == null) return [];
    return (jsonDecode(raw) as List).cast<String>();
  }

  Future<void> add(String term) async {
    final trimmed = term.trim();
    if (trimmed.isEmpty) return;
    final current = state.value ?? [];
    final updated = [trimmed, ...current.where((t) => t != trimmed)].take(_maxHistoryEntries).toList();
    await ref.read(appDatabaseProvider).setKeyValue(_searchHistoryKey, jsonEncode(updated));
    state = AsyncData(updated);
  }

  Future<void> clear() async {
    await ref.read(appDatabaseProvider).setKeyValue(_searchHistoryKey, jsonEncode(<String>[]));
    state = const AsyncData([]);
  }
}

final searchHistoryProvider = AsyncNotifierProvider<SearchHistoryController, List<String>>(
  SearchHistoryController.new,
);

/// C2 (client feedback): "subcategory selection... in search filters,
/// matching the website's behaviour against the same endpoint" — the
/// website's own `/search` page filters by category/subcategory through
/// this exact query param already; the app's search never sent one at
/// all. Same parent-vs-child split as the home feed's own
/// [selectedCategoryIdProvider]/[selectedSubcategoryIdProvider] pair, for
/// the same reason (a chip/dropdown showing "which top-level category" is
/// active shouldn't have to un-derive it from a child id).
final searchCategoryIdProvider = StateProvider<int?>((ref) => null);
final searchSubcategoryIdProvider = StateProvider<int?>((ref) => null);

class SearchResultsController extends AsyncNotifier<List<Product>> {
  String _lastQuery = '';

  @override
  Future<List<Product>> build() async => [];

  Future<void> search(String term) async {
    _lastQuery = term;
    if (term.trim().isEmpty) {
      state = const AsyncData([]);
      return;
    }
    state = const AsyncLoading();
    state = await AsyncValue.guard(() async {
      final result = await ref.read(productRepositoryProvider).products(
        query: term,
        sort: 'newest',
        categoryId: ref.read(searchSubcategoryIdProvider) ?? ref.read(searchCategoryIdProvider),
      );
      return result.items;
    });
  }

  /// Re-runs the last search term with the current category/subcategory
  /// filter — called after the filter sheet's "Apply" so a category
  /// change takes effect without the visitor having to retype anything.
  Future<void> applyFilters() => search(_lastQuery);
}

final searchResultsProvider = AsyncNotifierProvider<SearchResultsController, List<Product>>(
  SearchResultsController.new,
);
