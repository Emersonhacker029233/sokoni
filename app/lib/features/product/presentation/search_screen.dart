import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/l10n/gen/app_localizations.dart';
import '../../../core/network/api_exception.dart';
import '../../../core/router/routes.dart';
import '../../../core/theme/dimens.dart';
import '../../../shared/widgets/connectivity_banner.dart';
import '../../../shared/widgets/empty_state.dart';
import '../../../shared/widgets/error_state.dart';
import '../../../shared/widgets/product_card.dart';
import '../../../data/models/category.dart';
import '../../discovery/providers/discovery_providers.dart';
import '../providers/search_providers.dart';

/// Debounced search with history (CLAUDE.md feature 1: "debounced search
/// with history").
class SearchScreen extends ConsumerStatefulWidget {
  const SearchScreen({super.key});

  @override
  ConsumerState<SearchScreen> createState() => _SearchScreenState();
}

class _SearchScreenState extends ConsumerState<SearchScreen> {
  final _controller = TextEditingController();
  Timer? _debounce;

  @override
  void dispose() {
    _debounce?.cancel();
    _controller.dispose();
    super.dispose();
  }

  void _onChanged(String value) {
    _debounce?.cancel();
    _debounce = Timer(const Duration(milliseconds: 400), () {
      ref.read(searchResultsProvider.notifier).search(value);
    });
  }

  void _onSubmitted(String value) {
    _debounce?.cancel();
    ref.read(searchResultsProvider.notifier).search(value);
    ref.read(searchHistoryProvider.notifier).add(value);
  }

  void _selectHistory(String term) {
    _controller.text = term;
    ref.read(searchResultsProvider.notifier).search(term);
  }

  Future<void> _showCategoryFilterSheet(BuildContext context, WidgetRef ref) {
    return showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      builder: (sheetContext) => const _SearchCategoryFilterSheet(),
    );
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    final results = ref.watch(searchResultsProvider);
    final history = ref.watch(searchHistoryProvider);

    final categoryId = ref.watch(searchCategoryIdProvider);
    final subcategoryId = ref.watch(searchSubcategoryIdProvider);
    final hasCategoryFilter = categoryId != null || subcategoryId != null;

    return Scaffold(
      appBar: AppBar(
        title: TextField(
          controller: _controller,
          autofocus: true,
          textInputAction: TextInputAction.search,
          decoration: InputDecoration(
            hintText: l10n.searchHint,
            border: InputBorder.none,
          ),
          onChanged: _onChanged,
          onSubmitted: _onSubmitted,
        ),
        actions: [
          IconButton(
            icon: Icon(hasCategoryFilter ? Icons.filter_alt_rounded : Icons.filter_alt_outlined),
            tooltip: l10n.searchFiltersAction,
            onPressed: () => _showCategoryFilterSheet(context, ref),
          ),
        ],
      ),
      body: _controller.text.isEmpty
          ? _HistoryList(history: history.value ?? [], onSelect: _selectHistory)
          : ConnectivityBanner(
              child: results.when(
                loading: () => const Center(child: CircularProgressIndicator()),
                error: (error, _) => SokoniErrorState(
                  message: error is ApiException
                      ? error.message
                      : l10n.feedErrorBody,
                  onRetry: () => ref
                      .read(searchResultsProvider.notifier)
                      .search(_controller.text),
                ),
                data: (items) => items.isEmpty
                    ? SokoniEmptyState(
                        icon: Icons.search_off_rounded,
                        title: l10n.feedEmptyTitle,
                        message: l10n.feedEmptyBody,
                      )
                    : GridView.builder(
                        padding: const EdgeInsets.all(SokoniDimens.space16),
                        gridDelegate:
                            const SliverGridDelegateWithFixedCrossAxisCount(
                              crossAxisCount: 2,
                              mainAxisSpacing: SokoniDimens.space12,
                              crossAxisSpacing: SokoniDimens.space12,
                              childAspectRatio: 0.62,
                            ),
                        itemCount: items.length,
                        itemBuilder: (context, index) => ProductCard(
                          product: items[index],
                          onTap: () => context.push(
                            SokoniRoutes.product(items[index].id),
                          ),
                        ),
                      ),
              ),
            ),
    );
  }
}

class _HistoryList extends StatelessWidget {
  const _HistoryList({required this.history, required this.onSelect});

  final List<String> history;
  final ValueChanged<String> onSelect;

  @override
  Widget build(BuildContext context) {
    if (history.isEmpty) {
      final l10n = AppLocalizations.of(context);
      return SokoniEmptyState(
        icon: Icons.search_rounded,
        title: l10n.searchComingSoonTitle,
        message: l10n.searchHint,
      );
    }
    return ListView.builder(
      itemCount: history.length,
      itemBuilder: (context, index) => ListTile(
        leading: const Icon(Icons.history_rounded),
        title: Text(history[index]),
        onTap: () => onSelect(history[index]),
      ),
    );
  }
}

/// C2 (client feedback): "subcategory selection... in search filters,
/// matching the website's behaviour against the same endpoint" — the
/// same category/subcategory pair the website's own search page filters
/// on, via [ProductRepository.products]'s existing `categoryId` param.
class _SearchCategoryFilterSheet extends ConsumerStatefulWidget {
  const _SearchCategoryFilterSheet();

  @override
  ConsumerState<_SearchCategoryFilterSheet> createState() => _SearchCategoryFilterSheetState();
}

class _SearchCategoryFilterSheetState extends ConsumerState<_SearchCategoryFilterSheet> {
  late int? _categoryId = ref.read(searchCategoryIdProvider);
  late int? _subcategoryId = ref.read(searchSubcategoryIdProvider);

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    final categoriesAsync = ref.watch(topLevelCategoriesProvider);

    return Padding(
      padding: EdgeInsets.only(
        left: SokoniDimens.space20,
        right: SokoniDimens.space20,
        top: SokoniDimens.space20,
        bottom: MediaQuery.viewInsetsOf(context).bottom + SokoniDimens.space24,
      ),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(l10n.searchFiltersAction, style: Theme.of(context).textTheme.titleLarge),
          const SizedBox(height: SokoniDimens.space16),
          categoriesAsync.when(
            loading: () => const LinearProgressIndicator(),
            error: (error, _) => const SizedBox.shrink(),
            data: (categories) => DropdownButtonFormField<int?>(
              initialValue: _categoryId,
              decoration: InputDecoration(labelText: l10n.productFormCategory),
              items: [
                DropdownMenuItem(value: null, child: Text(l10n.categoryAll)),
                for (final c in categories) DropdownMenuItem(value: c.id, child: Text(c.name(Localizations.localeOf(context).languageCode))),
              ],
              onChanged: (value) => setState(() {
                _categoryId = value;
                _subcategoryId = null;
              }),
            ),
          ),
          if (_categoryId != null)
            Consumer(
              builder: (context, ref, _) {
                final subcategoriesAsync = ref.watch(subcategoriesProvider(_categoryId!));
                return subcategoriesAsync.when(
                  loading: () => const LinearProgressIndicator(),
                  error: (error, _) => const SizedBox.shrink(),
                  data: (subcategories) {
                    if (subcategories.isEmpty) return const SizedBox.shrink();
                    return Padding(
                      padding: const EdgeInsets.only(top: SokoniDimens.space16),
                      child: DropdownButtonFormField<int?>(
                        initialValue: _subcategoryId,
                        decoration: InputDecoration(labelText: l10n.productFormSubcategory),
                        items: [
                          DropdownMenuItem(value: null, child: Text(l10n.categoryAll)),
                          for (final c in subcategories)
                            DropdownMenuItem(value: c.id, child: Text(c.name(Localizations.localeOf(context).languageCode))),
                        ],
                        onChanged: (value) => setState(() => _subcategoryId = value),
                      ),
                    );
                  },
                );
              },
            ),
          const SizedBox(height: SokoniDimens.space20),
          FilledButton(
            onPressed: () {
              ref.read(searchCategoryIdProvider.notifier).state = _categoryId;
              ref.read(searchSubcategoryIdProvider.notifier).state = _subcategoryId;
              ref.read(searchResultsProvider.notifier).applyFilters();
              Navigator.of(context).pop();
            },
            child: Text(l10n.commonApply),
          ),
        ],
      ),
    );
  }
}
