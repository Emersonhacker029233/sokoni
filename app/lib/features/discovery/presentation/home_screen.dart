import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/l10n/gen/app_localizations.dart';
import '../../../core/motion/motion.dart';
import '../../../core/network/api_exception.dart';
import '../../../core/router/routes.dart';
import '../../../core/theme/colors.dart';
import '../../../core/theme/dimens.dart';
import '../../../data/models/category.dart';
import '../../../data/models/product.dart';
import '../../../data/models/seller_summary.dart';
import '../../../shared/widgets/connectivity_banner.dart';
import '../../../shared/widgets/empty_state.dart';
import '../../../shared/widgets/error_state.dart';
import '../../../shared/widgets/product_card.dart';
import '../providers/discovery_location.dart';
import '../providers/discovery_providers.dart';
import 'widgets/discovery_map_view.dart';
import 'widgets/district_picker_sheet.dart';
import 'widgets/radius_filter_sheet.dart';
import 'widgets/sticky_category_header.dart';

/// Home tab: Near You / Trending / New Sellers feed with sticky animated
/// category chips, radius filter, and a clustered map toggle (CLAUDE.md
/// feature 1). List view ships here; the map toggle is the next slice of
/// this feature.
class HomeScreen extends ConsumerStatefulWidget {
  const HomeScreen({super.key});

  @override
  ConsumerState<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends ConsumerState<HomeScreen> {
  final _scrollController = ScrollController();

  @override
  void initState() {
    super.initState();
    _scrollController.addListener(_onScroll);
  }

  @override
  void dispose() {
    _scrollController.removeListener(_onScroll);
    _scrollController.dispose();
    super.dispose();
  }

  void _onScroll() {
    if (_scrollController.position.pixels > _scrollController.position.maxScrollExtent - 400) {
      ref.read(discoveryFeedProvider.notifier).loadMore();
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    final feed = ref.watch(discoveryFeedProvider);
    final location = ref.watch(discoveryLocationProvider);

    ref.listen(discoveryLocationProvider, (previous, next) {
      final needsPicker = next.value?.needsFallbackPicker ?? false;
      final alreadyHandled = previous?.value?.needsFallbackPicker ?? false;
      if (needsPicker && !alreadyHandled && mounted) {
        _promptForArea(context);
      }
    });

    final isMapView = ref.watch(feedIsMapViewProvider);

    return Scaffold(
      appBar: AppBar(
        title: Text(l10n.appName),
        actions: [
          IconButton(
            icon: Icon(isMapView ? Icons.list_rounded : Icons.map_outlined),
            onPressed: () => ref.read(feedIsMapViewProvider.notifier).state = !isMapView,
          ),
          IconButton(
            icon: const Icon(Icons.tune_rounded),
            onPressed: () async {
              final picked = await showRadiusFilterSheet(context, ref.read(radiusPresetProvider));
              if (picked != null) ref.read(radiusPresetProvider.notifier).state = picked;
            },
          ),
        ],
      ),
      body: isMapView
          ? feed.when(
              loading: () => const Center(child: CircularProgressIndicator()),
              error: (error, _) => SokoniErrorState(
                message: error is ApiException ? error.message : l10n.feedErrorBody,
                onRetry: () => ref.read(discoveryFeedProvider.notifier).refresh(),
              ),
              data: (state) => DiscoveryMapView(
                products: state.items,
                center: location.value?.coords != null
                    ? (location.value!.coords!.lat, location.value!.coords!.lng)
                    : const (-6.7924, 39.2083), // Dar es Salaam city centre fallback.
                onSellerTap: (seller) => _showSellerQuickView(context, seller),
              ),
            )
          : ConnectivityBanner(
        child: SokoniRefreshIndicator(
          onRefresh: () => ref.read(discoveryFeedProvider.notifier).refresh(),
          child: CustomScrollView(
            controller: _scrollController,
            slivers: [
              SliverPersistentHeader(
                pinned: true,
                delegate: StickyCategoryHeaderDelegate(child: _CategoryChips()),
              ),
              SliverPadding(
                padding: const EdgeInsets.symmetric(horizontal: SokoniDimens.space16, vertical: SokoniDimens.space8),
                sliver: _SortSelector(),
              ),
              if (location.isLoading && !location.hasValue)
                const SliverToBoxAdapter(child: SizedBox.shrink()),
              feed.when(
                loading: () => SliverPadding(
                  padding: const EdgeInsets.all(SokoniDimens.space16),
                  sliver: _SkeletonGrid(),
                ),
                error: (error, _) => SliverFillRemaining(
                  hasScrollBody: false,
                  child: SokoniErrorState(
                    message: error is ApiException ? error.message : l10n.feedErrorBody,
                    onRetry: () => ref.read(discoveryFeedProvider.notifier).refresh(),
                  ),
                ),
                data: (state) => state.items.isEmpty
                    ? SliverFillRemaining(
                        hasScrollBody: false,
                        child: SokoniEmptyState(
                          icon: Icons.storefront_outlined,
                          title: l10n.feedEmptyTitle,
                          message: l10n.feedEmptyBody,
                        ),
                      )
                    : _ProductGrid(items: state.items),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Future<void> _showSellerQuickView(BuildContext context, SellerSummary seller) {
    return showSokoniBottomSheet<void>(
      context: context,
      initialChildSize: 0.32,
      builder: (context) => Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              if (seller.isVerified)
                const Padding(
                  padding: EdgeInsets.only(right: 4),
                  child: Icon(Icons.verified_rounded, color: SokoniColors.sokoniYellow, size: 18),
                ),
              Expanded(
                child: Text(seller.shopName, style: Theme.of(context).textTheme.titleMedium),
              ),
            ],
          ),
          const SizedBox(height: 4),
          Text('@${seller.handle}'),
          const SizedBox(height: 8),
          Row(
            children: [
              const Icon(Icons.star_rounded, size: 16, color: SokoniColors.sokoniYellow),
              const SizedBox(width: 4),
              Text('${seller.ratingAvg.toStringAsFixed(1)} (${seller.ratingCount})'),
            ],
          ),
          const SizedBox(height: SokoniDimens.space16),
          FilledButton(
            onPressed: () {
              Navigator.of(context).pop();
              context.push(SokoniRoutes.shop(seller.handle));
            },
            child: Text(AppLocalizations.of(context).shopViewAction),
          ),
        ],
      ),
    );
  }

  Future<void> _promptForArea(BuildContext context) async {
    final l10n = AppLocalizations.of(context);
    final choice = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: Text(l10n.locationPermissionTitle),
        content: Text(l10n.locationPermissionBody),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(context).pop(false),
            child: Text(l10n.locationChooseArea),
          ),
        ],
      ),
    );
    if (choice == false && context.mounted) {
      final district = await showDistrictPickerSheet(context);
      if (district != null) {
        ref.read(selectedDistrictProvider.notifier).state = district;
      }
    }
  }
}

class _CategoryChips extends ConsumerWidget {
  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = AppLocalizations.of(context);
    final categoriesAsync = ref.watch(categoriesProvider);
    final selected = ref.watch(selectedCategoryIdProvider);
    final locale = Localizations.localeOf(context).languageCode;

    return categoriesAsync.when(
      loading: () => const SizedBox(height: 44),
      error: (error, _) => const SizedBox(height: 44),
      data: (categories) {
        final labels = [l10n.categoryAll, ...categories.map((c) => c.name(locale))];
        final selectedIndex = selected == null
            ? 0
            : categories.indexWhere((c) => c.id == selected) + 1;

        return AnimatedChipRow(
          labels: labels,
          selectedIndex: selectedIndex < 0 ? 0 : selectedIndex,
          onSelected: (index) {
            ref.read(selectedCategoryIdProvider.notifier).state =
                index == 0 ? null : categories[index - 1].id;
          },
        );
      },
    );
  }
}

class _SortSelector extends ConsumerWidget {
  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = AppLocalizations.of(context);
    final sort = ref.watch(feedSortProvider);

    Widget segment(FeedSort value, String label) {
      final selected = sort == value;
      return Padding(
        padding: const EdgeInsets.only(right: SokoniDimens.space8),
        child: ChoiceChip(
          label: Text(label),
          selected: selected,
          onSelected: (_) => ref.read(feedSortProvider.notifier).state = value,
        ),
      );
    }

    return SliverToBoxAdapter(
      child: SingleChildScrollView(
        scrollDirection: Axis.horizontal,
        child: Row(
          children: [
            segment(FeedSort.nearby, l10n.sortNearby),
            segment(FeedSort.trending, l10n.sortTrending),
            segment(FeedSort.newest, l10n.sortNewest),
          ],
        ),
      ),
    );
  }
}

class _ProductGrid extends StatelessWidget {
  const _ProductGrid({required this.items});

  final List<Product> items;

  @override
  Widget build(BuildContext context) {
    const staggeredCount = 8;
    return SliverPadding(
      padding: const EdgeInsets.all(SokoniDimens.space16),
      sliver: SliverGrid(
        gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
          crossAxisCount: 2,
          mainAxisSpacing: SokoniDimens.space12,
          crossAxisSpacing: SokoniDimens.space12,
          childAspectRatio: 0.62,
        ),
        delegate: SliverChildBuilderDelegate(
          (context, index) {
            final product = items[index];
            final card = ProductCard(
              product: product,
              onTap: () => context.push(SokoniRoutes.product(product.id)),
            );
            if (index >= staggeredCount) return card;
            return _StaggeredGridItem(index: index, child: card);
          },
          childCount: items.length,
        ),
      ),
    );
  }
}

/// Applies the same fade+rise reveal as [StaggeredReveal], but per-grid-cell
/// (a plain [StaggeredReveal] assumes a linear Column/Row, not a grid).
class _StaggeredGridItem extends StatefulWidget {
  const _StaggeredGridItem({required this.index, required this.child});

  final int index;
  final Widget child;

  @override
  State<_StaggeredGridItem> createState() => _StaggeredGridItemState();
}

class _StaggeredGridItemState extends State<_StaggeredGridItem>
    with SingleTickerProviderStateMixin {
  late final AnimationController _controller;
  bool _started = false;

  @override
  void initState() {
    super.initState();
    _controller = AnimationController(vsync: this, duration: const Duration(milliseconds: 350));
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (_started) return;
    _started = true;
    final delay = Duration(milliseconds: widget.index * 40);
    if (MediaQuery.disableAnimationsOf(context)) {
      _controller.value = 1;
    } else {
      Future.delayed(delay, () {
        if (mounted) _controller.forward();
      });
    }
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return AnimatedBuilder(
      animation: _controller,
      builder: (context, _) {
        final t = Curves.easeOutCubic.transform(_controller.value);
        return Opacity(
          opacity: t,
          child: Transform.translate(offset: Offset(0, (1 - t) * 16), child: widget.child),
        );
      },
    );
  }
}

class _SkeletonGrid extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    return SliverGrid(
      gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
        crossAxisCount: 2,
        mainAxisSpacing: SokoniDimens.space12,
        crossAxisSpacing: SokoniDimens.space12,
        childAspectRatio: 0.62,
      ),
      delegate: SliverChildBuilderDelegate(
        (context, index) => const ProductCardSkeleton(),
        childCount: 6,
      ),
    );
  }
}
