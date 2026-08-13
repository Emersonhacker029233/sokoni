import 'dart:convert';

import 'package:flutter_riverpod/flutter_riverpod.dart';

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

class SearchResultsController extends AsyncNotifier<List<Product>> {
  @override
  Future<List<Product>> build() async => [];

  Future<void> search(String term) async {
    if (term.trim().isEmpty) {
      state = const AsyncData([]);
      return;
    }
    state = const AsyncLoading();
    state = await AsyncValue.guard(() async {
      final result = await ref.read(productRepositoryProvider).products(query: term, sort: 'newest');
      return result.items;
    });
  }
}

final searchResultsProvider = AsyncNotifierProvider<SearchResultsController, List<Product>>(
  SearchResultsController.new,
);
