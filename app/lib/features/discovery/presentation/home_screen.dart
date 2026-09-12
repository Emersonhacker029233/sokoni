import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/l10n/gen/app_localizations.dart';
import '../../../core/motion/motion.dart';
import '../../../core/network/api_exception.dart';
import '../../../core/providers.dart';
import '../../../core/router/app_shell.dart';
import '../../../core/router/routes.dart';
import '../../../core/theme/colors.dart';
import '../../../core/theme/dimens.dart';
import '../../../data/models/category.dart';
import '../../../data/models/product.dart';
import '../../../data/models/seller_summary.dart';
import '../../../shared/widgets/connectivity_banner.dart';
import '../../../shared/widgets/empty_state.dart';
import '../../../shared/widgets/error_state.dart';
import '../../../shared/widgets/feed_card.dart';
import '../../../shared/widgets/product_card.dart';
import '../../chat/providers/chat_providers.dart';
import '../../notifications/providers/notification_providers.dart';
import '../../orders/providers/order_providers.dart';
import '../../social/presentation/widgets/offers_row.dart';
import '../../social/presentation/widgets/showcase_preview_row.dart';
import '../../social/presentation/widgets/updates_tray.dart';
import '../../social/providers/social_providers.dart';
import '../providers/discovery_location.dart';
import '../providers/discovery_providers.dart';
import '../providers/feed_providers.dart';
import 'widgets/discovery_map_view.dart';
import 'widgets/district_picker_sheet.dart';
import 'widgets/cars_filter_sheet.dart';
import 'widgets/radius_filter_sheet.dart';
import 'widgets/sticky_category_header.dart';

/// Home tab (CLAUDE.md Part 3): a top bar (wordmark, Saved/Chat/
/// Notifications), the Updates tray, then two tabs — "For You" (a real
/// single-column social feed, default) and "Shops" (the Near You/Trending/
/// New Sellers grid this screen used to be exclusively). Tab content
/// cross-fades via the same [FadeThroughIndexedStack] the bottom nav shell
/// already uses (motion primitive 1: "fade-through for tabs") rather than
/// inventing a second tab-switch animation.
class HomeScreen extends ConsumerWidget {
  const HomeScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = AppLocalizations.of(context);
    final tab = ref.watch(homeTabProvider);

    return Scaffold(
      appBar: AppBar(
        // The docblock above always called for a wordmark here, but this
        // rendered as plain text with no actual brand mark image at all —
        // tester feedback B2 ("the app bar logo is missing"). The asset
        // itself was already bundled correctly (used by the splash screen
        // and pull-to-refresh indicator) and just never referenced here.
        title: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            ClipRRect(
              borderRadius: BorderRadius.circular(6),
              child: Image.asset('assets/brand/sokoni_logo_icon.png', width: 28, height: 28),
            ),
            const SizedBox(width: SokoniDimens.space8),
            Text(l10n.appName),
          ],
        ),
        actions: [
          IconButton(
            tooltip: l10n.favoritesTitle,
            icon: const Icon(Icons.bookmark_border_rounded),
            onPressed: () => context.push(SokoniRoutes.favorites),
          ),
          IconButton(
            tooltip: l10n.chatTooltip,
            icon: BadgeBounce(
              icon: const Icon(Icons.chat_bubble_outline_rounded),
              count: ref.watch(conversationsProvider).value?.fold<int>(0, (sum, c) => sum + c.unreadCount) ?? 0,
            ),
            onPressed: () => context.push(SokoniRoutes.conversations),
          ),
          IconButton(
            tooltip: l10n.notificationsTitle,
            icon: BadgeBounce(
              icon: const Icon(Icons.notifications_none_rounded),
              count: ref.watch(unreadNotificationCountProvider),
            ),
            onPressed: () => context.push(SokoniRoutes.notifications),
          ),
        ],
      ),
      body: Column(
        children: [
          const UpdatesTray(),
          _HomeTabSelector(selected: tab),
          Expanded(
            child: FadeThroughIndexedStack(
              index: tab.index,
              children: const [_ForYouTabBody(), _ShopsTabBody()],
            ),
          ),
        ],
      ),
    );
  }
}

class _HomeTabSelector extends ConsumerWidget {
  const _HomeTabSelector({required this.selected});

  final HomeTab selected;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = AppLocalizations.of(context);
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final outline = isDark ? SokoniColors.darkOutline : SokoniColors.outline;

    Widget segment(HomeTab value, String label) {
      final isSelected = selected == value;
      return Expanded(
        child: InkWell(
          onTap: () => ref.read(homeTabProvider.notifier).state = value,
          child: Container(
            padding: const EdgeInsets.symmetric(vertical: SokoniDimens.space12),
            decoration: BoxDecoration(
              border: Border(
                bottom: BorderSide(color: isSelected ? SokoniColors.sokoniYellow : outline, width: isSelected ? 2 : 1),
              ),
            ),
            child: Text(
              label,
              textAlign: TextAlign.center,
              style: TextStyle(fontWeight: isSelected ? FontWeight.w700 : FontWeight.w500),
            ),
          ),
        ),
      );
    }

    return Row(
      children: [
        segment(HomeTab.forYou, l10n.homeForYouTab),
        segment(HomeTab.shops, l10n.homeShopsTab),
      ],
    );
  }
}

class _ForYouTabBody extends ConsumerStatefulWidget {
  const _ForYouTabBody();

  @override
  ConsumerState<_ForYouTabBody> createState() => _ForYouTabBodyState();
}

class _ForYouTabBodyState extends ConsumerState<_ForYouTabBody> {
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
    if (_scrollController.position.pixels > _scrollController.position.maxScrollExtent - 600) {
      ref.read(forYouFeedProvider.notifier).loadMore();
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    final feed = ref.watch(forYouFeedProvider);

    return ConnectivityBanner(
      child: SokoniRefreshIndicator(
        onRefresh: () => ref.read(forYouFeedProvider.notifier).refresh(),
        child: feed.when(
          loading: () => ListView.builder(
            padding: EdgeInsets.zero,
            itemCount: 4,
            itemBuilder: (context, index) => const _FeedCardSkeleton(),
          ),
          error: (error, _) => ListView(
            children: [
              SizedBox(
                height: 400,
                child: SokoniErrorState(
                  message: error is ApiException ? error.message : l10n.feedErrorBody,
                  onRetry: () => ref.read(forYouFeedProvider.notifier).refresh(),
                ),
              ),
            ],
          ),
          data: (state) => state.items.isEmpty
              ? ListView(
                  children: [
                    SizedBox(
                      height: 400,
                      child: SokoniEmptyState(
                        icon: Icons.dynamic_feed_outlined,
                        title: l10n.feedEmptyForYouTitle,
                        message: l10n.feedEmptyForYouBody,
                      ),
                    ),
                  ],
                )
              : ListView.builder(
                  controller: _scrollController,
                  padding: EdgeInsets.zero,
                  itemCount: state.items.length,
                  itemBuilder: (context, index) => FeedCard(item: state.items[index]),
                ),
        ),
      ),
    );
  }
}

class _FeedCardSkeleton extends StatelessWidget {
  const _FeedCardSkeleton();

  @override
  Widget build(BuildContext context) {
    return const SokoniShimmer(
      child: Padding(
        padding: EdgeInsets.symmetric(vertical: SokoniDimens.space12),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Padding(
              padding: EdgeInsets.symmetric(horizontal: SokoniDimens.space16),
              child: Row(
                children: [
                  SkeletonCircle(diameter: 36),
                  SizedBox(width: SokoniDimens.space8),
                  SkeletonLine(width: 120),
                ],
              ),
            ),
            SizedBox(height: SokoniDimens.space8),
            AspectRatio(
              aspectRatio: 4 / 5,
              child: SkeletonBox(width: double.infinity, height: double.infinity, borderRadius: 0),
            ),
          ],
        ),
      ),
    );
  }
}

class _ShopsTabBody extends ConsumerStatefulWidget {
  const _ShopsTabBody();

  @override
  ConsumerState<_ShopsTabBody> createState() => _ShopsTabBodyState();
}

class _ShopsTabBodyState extends ConsumerState<_ShopsTabBody> {
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

    // The toolbar (cart, map⇄list toggle, radius filter) sits above both
    // view modes, not just the list one — `DiscoveryMapView` itself has no
    // persistent "back to list" control once a real map renders (only in
    // its maps-unavailable empty-state fallback), so nesting the toggle
    // inside the list-only CustomScrollView would strand a user who
    // switches to map view with no way back.
    return Column(
      children: [
        Padding(
          padding: const EdgeInsets.only(top: SokoniDimens.space8),
          child: _ShopsToolbar(isMapView: isMapView),
        ),
        Expanded(
          child: isMapView
              ? ConnectivityBanner(
                  child: feed.when(
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
                      onSwitchToList: () => ref.read(feedIsMapViewProvider.notifier).state = false,
                    ),
                  ),
                )
              : ConnectivityBanner(
                  child: SokoniRefreshIndicator(
                    onRefresh: () => ref.read(discoveryFeedProvider.notifier).refresh(),
                    child: CustomScrollView(
                      controller: _scrollController,
                      slivers: [
                        const SliverToBoxAdapter(child: _FollowingFilterChip()),
                        SliverPersistentHeader(
                          pinned: true,
                          delegate: StickyCategoryHeaderDelegate(child: _CategoryChips()),
                        ),
                        const SliverToBoxAdapter(child: _SubcategoryChips()),
                        const SliverToBoxAdapter(
                          child: Padding(padding: EdgeInsets.only(top: SokoniDimens.space12), child: OffersRow()),
                        ),
                        const SliverToBoxAdapter(
                          child: Padding(
                            padding: EdgeInsets.only(top: SokoniDimens.space12),
                            child: ShowcasePreviewRow(),
                          ),
                        ),
                        SliverPadding(
                          padding: const EdgeInsets.symmetric(
                            horizontal: SokoniDimens.space16,
                            vertical: SokoniDimens.space8,
                          ),
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
        ),
      ],
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
              Expanded(child: Text(seller.shopName, style: Theme.of(context).textTheme.titleMedium)),
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
          TextButton(onPressed: () => Navigator.of(context).pop(false), child: Text(l10n.locationChooseArea)),
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

/// Cart, list⇄map toggle, and radius filter — moved here from the old
/// global app bar (CLAUDE.md Part 3: "keep [the top bar] uncluttered"),
/// since none of the three mean anything on the For You tab.
class _ShopsToolbar extends ConsumerWidget {
  const _ShopsToolbar({required this.isMapView});

  final bool isMapView;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    // C3 (tester feedback): "filters on Cars category page" — this app
    // browses categories via a flat top-level chip row rather than the
    // website's dedicated per-subcategory pages, so the Cars filter icon
    // is this app's equivalent entry point, shown once "Vehicles & Parts"
    // (Cars' parent) or Cars itself is the selected chip.
    final selectedCategory = ref.watch(selectedCategoryProvider);
    final showCarsFilter = selectedCategory != null && (selectedCategory.isCars || selectedCategory.nameEn == 'Vehicles & Parts');

    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: SokoniDimens.space8),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.end,
        children: [
          IconButton(
            icon: BadgeBounce(
              icon: const Icon(Icons.shopping_bag_outlined),
              count: ref.watch(cartProvider).fold(0, (sum, line) => sum + line.qty),
            ),
            onPressed: () => context.push(SokoniRoutes.cart),
          ),
          IconButton(
            icon: Icon(isMapView ? Icons.list_rounded : Icons.map_outlined),
            onPressed: () => ref.read(feedIsMapViewProvider.notifier).state = !isMapView,
          ),
          if (showCarsFilter)
            IconButton(
              icon: const Icon(Icons.directions_car_outlined),
              onPressed: () {
                final vehiclesPartsId = selectedCategory.isCars ? selectedCategory.parentId! : selectedCategory.id;
                showCarsFilterSheet(context, ref, vehiclesPartsId);
              },
            ),
          IconButton(
            icon: const Icon(Icons.tune_rounded),
            onPressed: () async {
              final picked = await showRadiusFilterSheet(context, ref.read(radiusPresetProvider));
              if (picked != null) {
                ref.read(radiusPresetProvider.notifier).state = picked;
              }
            },
          ),
        ],
      ),
    );
  }
}

class _CategoryChips extends ConsumerWidget {
  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = AppLocalizations.of(context);
    final categoriesAsync = ref.watch(topLevelCategoriesProvider);
    final selected = ref.watch(selectedCategoryIdProvider);
    final locale = Localizations.localeOf(context).languageCode;

    return categoriesAsync.when(
      loading: () => const SizedBox(height: 44),
      error: (error, _) => const SizedBox(height: 44),
      data: (categories) {
        final labels = [l10n.categoryAll, ...categories.map((c) => c.name(locale))];
        final selectedIndex = selected == null ? 0 : categories.indexWhere((c) => c.id == selected) + 1;

        return AnimatedChipRow(
          labels: labels,
          selectedIndex: selectedIndex < 0 ? 0 : selectedIndex,
          onSelected: (index) {
            ref.read(selectedCategoryIdProvider.notifier).state = index == 0 ? null : categories[index - 1].id;
            // Switching top-level categories leaves any Cars-specific
            // filter meaningless (and, worse, silently zeroing out an
            // unrelated category's results) — clear it here rather than
            // only when the Cars sheet itself is dismissed. Same reasoning
            // for the subcategory pick — it belongs to whichever parent
            // was previously selected, not the new one.
            ref.read(makeFilterProvider.notifier).state = null;
            ref.read(modelFilterProvider.notifier).state = null;
            ref.read(yearFilterProvider.notifier).state = null;
            ref.read(selectedSubcategoryIdProvider.notifier).state = null;
          },
        );
      },
    );
  }
}

/// C2 (client feedback): "the website has subcategories, the app doesn't
/// show them" — this is the app's equivalent of the website category
/// page's subcategory sidebar, adapted to the app's own flat top-level
/// chip idiom rather than a sidebar (there's no room for one on a phone).
/// Collapses to nothing both when no top-level category is selected and
/// when the selected one has no children — never an empty row.
class _SubcategoryChips extends ConsumerWidget {
  const _SubcategoryChips();

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = AppLocalizations.of(context);
    final parent = ref.watch(selectedCategoryProvider);
    if (parent == null) return const SizedBox.shrink();

    final subcategoriesAsync = ref.watch(subcategoriesProvider(parent.id));
    final locale = Localizations.localeOf(context).languageCode;

    return subcategoriesAsync.when(
      loading: () => const SizedBox.shrink(),
      error: (error, _) => const SizedBox.shrink(),
      data: (subcategories) {
        if (subcategories.isEmpty) return const SizedBox.shrink();

        final selected = ref.watch(selectedSubcategoryIdProvider);
        final labels = [l10n.categoryAll, ...subcategories.map((c) => c.name(locale))];
        final selectedIndex = selected == null ? 0 : subcategories.indexWhere((c) => c.id == selected) + 1;

        return Padding(
          padding: const EdgeInsets.only(top: SokoniDimens.space4),
          child: AnimatedChipRow(
            labels: labels,
            selectedIndex: selectedIndex < 0 ? 0 : selectedIndex,
            onSelected: (index) {
              ref.read(selectedSubcategoryIdProvider.notifier).state = index == 0 ? null : subcategories[index - 1].id;
            },
          ),
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
        delegate: SliverChildBuilderDelegate((context, index) {
          final product = items[index];
          final card = ProductCard(
            product: product,
            onTap: () => context.push(SokoniRoutes.product(product.id)),
          );
          if (index >= staggeredCount) return card;
          return _StaggeredGridItem(index: index, child: card);
        }, childCount: items.length),
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

class _StaggeredGridItemState extends State<_StaggeredGridItem> with SingleTickerProviderStateMixin {
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

/// "Following" filter (CLAUDE.md Part 3) — scopes the Updates tray, Offers
/// row and Showcase preview row to shops the signed-in buyer follows. Only
/// meaningful once signed in, so it's hidden entirely for a browsing guest
/// rather than shown disabled — CLAUDE.md feature 4: browsing works fully
/// without an account.
class _FollowingFilterChip extends ConsumerWidget {
  const _FollowingFilterChip();

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    if (!ref.watch(authStateProvider).isAuthenticated) return const SizedBox.shrink();

    final l10n = AppLocalizations.of(context);
    final followingOnly = ref.watch(socialFollowingOnlyProvider);

    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: SokoniDimens.space16, vertical: SokoniDimens.space4),
      child: Align(
        alignment: Alignment.centerRight,
        child: FilterChip(
          label: Text(l10n.followingFilterLabel),
          selected: followingOnly,
          onSelected: (value) => ref.read(socialFollowingOnlyProvider.notifier).state = value,
        ),
      ),
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
