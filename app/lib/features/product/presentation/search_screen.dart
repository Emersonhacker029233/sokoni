import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/l10n/gen/app_localizations.dart';
import '../../../core/network/api_exception.dart';
import '../../../core/router/routes.dart';
import '../../../core/theme/dimens.dart';
import '../../../shared/widgets/empty_state.dart';
import '../../../shared/widgets/error_state.dart';
import '../../../shared/widgets/product_card.dart';
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

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    final results = ref.watch(searchResultsProvider);
    final history = ref.watch(searchHistoryProvider);

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
      ),
      body: _controller.text.isEmpty
          ? _HistoryList(history: history.value ?? [], onSelect: _selectHistory)
          : results.when(
              loading: () => const Center(child: CircularProgressIndicator()),
              error: (error, _) => SokoniErrorState(
                message: error is ApiException ? error.message : l10n.feedErrorBody,
                onRetry: () => ref.read(searchResultsProvider.notifier).search(_controller.text),
              ),
              data: (items) => items.isEmpty
                  ? SokoniEmptyState(
                      icon: Icons.search_off_rounded,
                      title: l10n.feedEmptyTitle,
                      message: l10n.feedEmptyBody,
                    )
                  : GridView.builder(
                      padding: const EdgeInsets.all(SokoniDimens.space16),
                      gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                        crossAxisCount: 2,
                        mainAxisSpacing: SokoniDimens.space12,
                        crossAxisSpacing: SokoniDimens.space12,
                        childAspectRatio: 0.62,
                      ),
                      itemCount: items.length,
                      itemBuilder: (context, index) => ProductCard(
                        product: items[index],
                        onTap: () => context.push(SokoniRoutes.product(items[index].id)),
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
