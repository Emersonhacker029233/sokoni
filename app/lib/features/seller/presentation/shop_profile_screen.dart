import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:share_plus/share_plus.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../../core/l10n/gen/app_localizations.dart';
import '../../../core/network/api_exception.dart';
import '../../../core/router/routes.dart';
import '../../../core/theme/colors.dart';
import '../../../core/theme/dimens.dart';
import '../../../core/utils/opening_hours.dart';
import '../../../data/models/category.dart';
import '../../../data/models/review.dart';
import '../../../data/models/seller_profile.dart';
import '../../../shared/widgets/connectivity_banner.dart';
import '../../../shared/widgets/empty_state.dart';
import '../../../shared/widgets/error_state.dart';
import '../../../shared/widgets/report_sheet.dart';
import '../../../shared/widgets/shop_location_map.dart';
import '../../../shared/widgets/sokoni_avatar.dart';
import '../../auth/presentation/sign_in_prompt_sheet.dart';
import '../../auth/providers/auth_providers.dart';
import '../../discovery/presentation/widgets/sticky_category_header.dart';
import '../../social/presentation/widgets/showcase_thumb_grid.dart';
import '../../social/presentation/widgets/updates_list.dart';
import '../providers/seller_providers.dart';
import 'widgets/edit_shop_profile_sheet.dart';
import 'widgets/review_distribution_bar.dart';
import 'widgets/shop_listings_grid.dart';

/// Shop profile, Instagram-style (CLAUDE.md Part 4): circular avatar,
/// three counts, bio/contact row, Edit+Share (owner) or Follow+Message
/// (everyone else), an owner-only 30-day dashboard strip, then icon-only
/// tabs (grid/play/person/star) over a 3-column grid. Deep-linkable at
/// `sokoni.co.tz/@handle` — see docs/DEPLOY.md for the web-fallback side
/// of that; in-app this route is `/shop/:handle`.
class ShopProfileScreen extends ConsumerWidget {
  const ShopProfileScreen({required this.handle, super.key});

  final String handle;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = AppLocalizations.of(context);
    final sellerAsync = ref.watch(sellerProfileProvider(handle));
    // Tester feedback (follower count not updating): toggling Follow now
    // invalidates this provider to reconcile the shown count against the
    // server — reading `.value` here rather than `.when()`'s `loading`
    // branch means that refetch keeps showing the current profile in
    // place while it's in flight, instead of blanking the whole screen to
    // a spinner on every single follow/unfollow tap.
    final seller = sellerAsync.value;

    return Scaffold(
      body: ConnectivityBanner(
        child: seller == null
            ? sellerAsync.when(
                loading: () => const Center(child: CircularProgressIndicator()),
                error: (error, _) => SokoniErrorState(
                  message: error is ApiException
                      ? error.message
                      : l10n.feedErrorBody,
                  onRetry: () => ref.invalidate(sellerProfileProvider(handle)),
                ),
                data: (_) => const SizedBox.shrink(),
              )
            : DefaultTabController(
                length: 4,
                child: NestedScrollView(
                  headerSliverBuilder: (context, innerBoxIsScrolled) => [
                    SliverAppBar(
                      pinned: false,
                      title: Text('@${seller.handle}'),
                      actions: [
                        IconButton(
                          icon: const Icon(Icons.ios_share_rounded),
                          onPressed: () => SharePlus.instance.share(
                            ShareParams(
                              text: 'https://sokoni.co.tz/@${seller.handle}',
                            ),
                          ),
                        ),
                        IconButton(
                          icon: const Icon(Icons.flag_outlined),
                          onPressed: () => showReportSheet(
                            context,
                            ref,
                            reportableType: 'shop',
                            reportableId: seller.id,
                          ),
                        ),
                      ],
                    ),
                    SliverToBoxAdapter(child: _ProfileHeader(seller: seller)),
                    SliverPersistentHeader(
                      pinned: true,
                      delegate: StickyCategoryHeaderDelegate(
                        height: 48,
                        child: const _ShopTabBar(),
                      ),
                    ),
                  ],
                  body: TabBarView(
                    children: [
                      ShopListingsGrid(sellerId: seller.id),
                      ShowcaseThumbGrid(sellerId: seller.id),
                      UpdatesList(sellerId: seller.id),
                      _ShopReviewsTab(handle: handle),
                    ],
                  ),
                ),
              ),
      ),
    );
  }
}

class _ShopTabBar extends StatelessWidget {
  const _ShopTabBar();

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    return TabBar(
      tabs: [
        Tab(
          icon: const Icon(Icons.grid_on_outlined),
          child: Text(l10n.shopProductsTitle),
        ),
        Tab(
          icon: const Icon(Icons.play_circle_outline_rounded),
          child: Text(l10n.shopShowcaseTitle),
        ),
        Tab(
          icon: const Icon(Icons.person_outline_rounded),
          child: Text(l10n.shopUpdatesTitle),
        ),
        Tab(
          icon: const Icon(Icons.star_outline_rounded),
          child: Text(l10n.shopReviewsTitle),
        ),
      ],
    );
  }
}

class _ProfileHeader extends ConsumerWidget {
  const _ProfileHeader({required this.seller});

  final SellerProfile seller;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = AppLocalizations.of(context);
    final textTheme = Theme.of(context).textTheme;

    return Padding(
      padding: const EdgeInsets.all(SokoniDimens.space16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.center,
            children: [
              SokoniAvatar(
                imageUrl: seller.logo,
                radius: 36,
                backgroundColor: SokoniColors.surfaceAlt,
                fallbackIcon: Icons.storefront_outlined,
                fallbackIconSize: 32,
              ),
              const SizedBox(width: SokoniDimens.space20),
              Expanded(
                child: Row(
                  mainAxisAlignment: MainAxisAlignment.spaceAround,
                  children: [
                    _CountColumn(
                      count: seller.productsCount,
                      label: l10n.shopProductsTitle,
                    ),
                    _CountColumn(
                      count: displayedCustomerCount(
                        ref,
                        sellerId: seller.id,
                        baseCount: seller.customerCount,
                        baseIsFollowing: seller.isFollowing,
                      ),
                      label: l10n.customerCountLabel,
                    ),
                    _CountColumn(
                      count: seller.followingCount,
                      label: l10n.followingFilterLabel,
                    ),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: SokoniDimens.space12),
          Row(
            children: [
              Flexible(
                child: Text(
                  seller.shopName,
                  style: textTheme.titleMedium?.copyWith(
                    fontWeight: FontWeight.w700,
                  ),
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                ),
              ),
              if (seller.isVerified)
                const Padding(
                  padding: EdgeInsets.only(left: 4),
                  child: Icon(
                    Icons.verified_rounded,
                    size: 16,
                    color: SokoniColors.sokoniYellow,
                  ),
                ),
            ],
          ),
          if (seller.category != null)
            Text(
              seller.category!.name(
                Localizations.localeOf(context).languageCode,
              ),
              style: textTheme.bodySmall,
            ),
          if (seller.bio != null && seller.bio!.isNotEmpty) ...[
            const SizedBox(height: SokoniDimens.space4),
            Text(seller.bio!, style: textTheme.bodyMedium),
          ],
          if (seller.address != null || seller.district != null) ...[
            const SizedBox(height: SokoniDimens.space4),
            Row(
              children: [
                Icon(
                  Icons.location_on_outlined,
                  size: 14,
                  color: Theme.of(context).hintColor,
                ),
                const SizedBox(width: 4),
                Expanded(
                  child: Text(
                    [
                      seller.district,
                      seller.region,
                    ].where((s) => s != null && s.isNotEmpty).join(', '),
                    style: textTheme.bodySmall,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                  ),
                ),
              ],
            ),
          ],
          if (seller.whatsapp != null) ...[
            const SizedBox(height: SokoniDimens.space4),
            GestureDetector(
              onTap: () => launchUrl(
                Uri.parse(
                  'https://wa.me/${seller.whatsapp!.replaceAll('+', '')}',
                ),
                mode: LaunchMode.externalApplication,
              ),
              child: Row(
                children: [
                  Icon(
                    Icons.phone_in_talk_outlined,
                    size: 14,
                    color: Theme.of(context).hintColor,
                  ),
                  const SizedBox(width: 4),
                  Text(seller.whatsapp!, style: textTheme.bodySmall),
                ],
              ),
            ),
          ],
          const SizedBox(height: SokoniDimens.space4),
          Row(
            children: [
              Icon(
                seller.isVerified
                    ? Icons.verified_rounded
                    : Icons.hourglass_empty_rounded,
                size: 14,
                color: seller.isVerified
                    ? SokoniColors.sokoniYellow
                    : Theme.of(context).hintColor,
              ),
              const SizedBox(width: 4),
              Text(
                seller.isVerified
                    ? l10n.shopVerifiedBadge
                    : l10n.shopPendingBadge,
                style: textTheme.bodySmall,
              ),
              const SizedBox(width: SokoniDimens.space12),
              const Icon(
                Icons.star_rounded,
                size: 14,
                color: SokoniColors.sokoniYellow,
              ),
              const SizedBox(width: 2),
              Text(
                '${seller.ratingAvg.toStringAsFixed(1)} (${seller.ratingCount})',
                style: textTheme.bodySmall,
              ),
            ],
          ),
          const SizedBox(height: SokoniDimens.space16),
          _ActionButtonsRow(seller: seller),
          // D3 (tester feedback): both exist on the website's shop page
          // already — the app's own profile screen never had either.
          if (seller.openingHours != null) ...[
            const SizedBox(height: SokoniDimens.space16),
            _OpeningHoursCard(openingHours: seller.openingHours!),
          ],
          if (seller.hasLocation ||
              (seller.address != null && seller.address!.isNotEmpty)) ...[
            const SizedBox(height: SokoniDimens.space16),
            ShopLocationMap(
              shopName: seller.shopName,
              address: seller.address,
              lat: seller.lat,
              lng: seller.lng,
            ),
          ],
          if (seller.isOwner) ...[
            const SizedBox(height: SokoniDimens.space16),
            _DashboardStrip(sellerId: seller.id),
          ],
        ],
      ),
    );
  }
}

/// D3 (tester feedback): the website's shop page already shows this — a
/// plain Monday-Sunday list, "Closed" for any day with no hours set.
class _OpeningHoursCard extends StatelessWidget {
  const _OpeningHoursCard({required this.openingHours});

  final Map<String, dynamic> openingHours;

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final outline = isDark ? SokoniColors.darkOutline : SokoniColors.outline;
    final dayLabels = {
      'monday': l10n.dayMonday,
      'tuesday': l10n.dayTuesday,
      'wednesday': l10n.dayWednesday,
      'thursday': l10n.dayThursday,
      'friday': l10n.dayFriday,
      'saturday': l10n.daySaturday,
      'sunday': l10n.daySunday,
    };

    return Container(
      padding: const EdgeInsets.all(SokoniDimens.space12),
      decoration: BoxDecoration(
        border: Border.all(color: outline),
        borderRadius: BorderRadius.circular(SokoniDimens.radiusCard),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            l10n.shopHoursTitle,
            style: Theme.of(context).textTheme.titleSmall,
          ),
          const SizedBox(height: SokoniDimens.space8),
          for (final day in SokoniOpeningHours.days)
            Padding(
              padding: const EdgeInsets.symmetric(vertical: 2),
              child: Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Text(
                    dayLabels[day]!,
                    style: Theme.of(context).textTheme.bodySmall,
                  ),
                  Builder(
                    builder: (context) {
                      final hours = SokoniOpeningHours.hoursFor(
                        openingHours,
                        day,
                      );
                      return Text(
                        hours != null
                            ? '${hours['open']} - ${hours['close']}'
                            : l10n.shopHoursClosed,
                        style: Theme.of(context).textTheme.bodySmall,
                      );
                    },
                  ),
                ],
              ),
            ),
        ],
      ),
    );
  }
}

class _CountColumn extends StatelessWidget {
  const _CountColumn({required this.count, required this.label});

  final int count;
  final String label;

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        Text(
          '$count',
          style: Theme.of(
            context,
          ).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w700),
        ),
        Text(label, style: Theme.of(context).textTheme.bodySmall),
      ],
    );
  }
}

class _ActionButtonsRow extends ConsumerWidget {
  const _ActionButtonsRow({required this.seller});

  final SellerProfile seller;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = AppLocalizations.of(context);

    if (seller.isOwner) {
      return Row(
        children: [
          Expanded(
            child: OutlinedButton(
              onPressed: () => showEditShopProfileSheet(context, ref, seller),
              child: Text(l10n.editProfileTitle),
            ),
          ),
          const SizedBox(width: SokoniDimens.space8),
          Expanded(
            child: OutlinedButton(
              onPressed: () => SharePlus.instance.share(
                ShareParams(text: 'https://sokoni.co.tz/@${seller.handle}'),
              ),
              child: Text(l10n.shareProfileAction),
            ),
          ),
        ],
      );
    }

    final following = isSellerFollowed(
      ref,
      sellerId: seller.id,
      isFollowing: seller.isFollowing,
    );

    return Row(
      children: [
        Expanded(
          child: following
              ? OutlinedButton(
                  onPressed: () => toggleSellerFollow(
                    ref,
                    sellerId: seller.id,
                    handle: seller.handle,
                    isFollowing: seller.isFollowing,
                    onUnauthenticated: () => showSignInPrompt(
                      context,
                      message: l10n.guestPromptFollow,
                    ),
                    onFailed: (message) => ScaffoldMessenger.of(
                      context,
                    ).showSnackBar(SnackBar(content: Text(message))),
                  ),
                  child: Text(l10n.followingAction),
                )
              : FilledButton(
                  onPressed: () => toggleSellerFollow(
                    ref,
                    sellerId: seller.id,
                    handle: seller.handle,
                    isFollowing: seller.isFollowing,
                    onUnauthenticated: () => showSignInPrompt(
                      context,
                      message: l10n.guestPromptFollow,
                    ),
                    onFailed: (message) => ScaffoldMessenger.of(
                      context,
                    ).showSnackBar(SnackBar(content: Text(message))),
                  ),
                  child: Text(l10n.followAction),
                ),
        ),
        const SizedBox(width: SokoniDimens.space8),
        Expanded(
          child: OutlinedButton(
            onPressed: () async {
              final conversationId = await startConversationOrPromptSignIn(
                ref,
                sellerId: seller.id,
                onUnauthenticated: () {
                  if (context.mounted) {
                    showSignInPrompt(context, message: l10n.guestPromptMessage);
                  }
                },
                onFailed: (message) {
                  if (context.mounted) {
                    ScaffoldMessenger.of(
                      context,
                    ).showSnackBar(SnackBar(content: Text(message)));
                  }
                },
              );
              if (conversationId != null && context.mounted) {
                await context.push(SokoniRoutes.conversation(conversationId));
              }
            },
            child: Text(l10n.productMessageSeller),
          ),
        ),
      ],
    );
  }
}

/// Owner-only 30-day stats strip (CLAUDE.md Part 4) — real data from
/// `SellerDashboardController`, not placeholder numbers.
class _DashboardStrip extends ConsumerWidget {
  const _DashboardStrip({required this.sellerId});

  final int sellerId;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = AppLocalizations.of(context);
    final statsAsync = ref.watch(sellerDashboardProvider(sellerId));
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final outline = isDark ? SokoniColors.darkOutline : SokoniColors.outline;

    return statsAsync.when(
      loading: () => const SizedBox(height: 56),
      error: (error, _) => const SizedBox.shrink(),
      data: (stats) => Container(
        padding: const EdgeInsets.symmetric(vertical: SokoniDimens.space12),
        decoration: BoxDecoration(
          border: Border.all(color: outline),
          borderRadius: BorderRadius.circular(SokoniDimens.radiusCard),
        ),
        child: Row(
          children: [
            Expanded(
              child: _StatColumn(
                value: stats.totalViews,
                label: l10n.dashboardTotalViews,
              ),
            ),
            Expanded(
              child: _StatColumn(
                value: stats.savesLast30Days,
                label: l10n.dashboardSaves30d,
              ),
            ),
            Expanded(
              child: _StatColumn(
                value: stats.ordersLast30Days,
                label: l10n.dashboardOrders30d,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _StatColumn extends StatelessWidget {
  const _StatColumn({required this.value, required this.label});

  final int value;
  final String label;

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        Text(
          '$value',
          style: Theme.of(
            context,
          ).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w700),
        ),
        Text(
          label,
          style: Theme.of(context).textTheme.bodySmall,
          textAlign: TextAlign.center,
        ),
      ],
    );
  }
}

class _ShopReviewsTab extends ConsumerWidget {
  const _ShopReviewsTab({required this.handle});

  final String handle;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = AppLocalizations.of(context);
    final reviewsAsync = ref.watch(sellerReviewsProvider(handle));

    return reviewsAsync.when(
      loading: () => const Center(child: CircularProgressIndicator()),
      error: (error, _) => SokoniErrorState(
        message: error is ApiException ? error.message : l10n.feedErrorBody,
        onRetry: () => ref.invalidate(sellerReviewsProvider(handle)),
      ),
      data: (data) {
        final (page, distribution) = data;
        if (page.items.isEmpty) {
          return SokoniEmptyState(
            icon: Icons.star_border_rounded,
            title: l10n.shopNoReviews,
            message: '',
          );
        }
        return ListView(
          padding: const EdgeInsets.all(SokoniDimens.space16),
          children: [
            ReviewDistributionBar(
              distribution: distribution,
              total: page.total,
            ),
            const SizedBox(height: SokoniDimens.space16),
            for (final review in page.items)
              _ReviewTile(review: review, shopHandle: handle),
          ],
        );
      },
    );
  }
}

class _ReviewTile extends ConsumerWidget {
  const _ReviewTile({required this.review, required this.shopHandle});

  final Review review;
  final String shopHandle;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = AppLocalizations.of(context);
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final surfaceAlt = isDark
        ? SokoniColors.darkSurfaceAlt
        : SokoniColors.surfaceAlt;
    final currentUserAsync = ref.watch(currentUserProvider);
    final isOwner = currentUserAsync.maybeWhen(
      data: (user) => user.sellerHandle == shopHandle,
      orElse: () => false,
    );

    return Padding(
      padding: const EdgeInsets.only(bottom: SokoniDimens.space16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Text(
                review.buyer?.name ?? '',
                style: Theme.of(
                  context,
                ).textTheme.bodyMedium?.copyWith(fontWeight: FontWeight.w600),
              ),
              const SizedBox(width: SokoniDimens.space8),
              Row(
                children: List.generate(
                  5,
                  (i) => Icon(
                    i < review.rating
                        ? Icons.star_rounded
                        : Icons.star_border_rounded,
                    size: 14,
                    color: SokoniColors.sokoniYellow,
                  ),
                ),
              ),
            ],
          ),
          if (review.comment != null) ...[
            const SizedBox(height: 4),
            Text(review.comment!),
          ],
          if (review.reply != null) ...[
            const SizedBox(height: 8),
            Container(
              padding: const EdgeInsets.all(SokoniDimens.space8),
              decoration: BoxDecoration(
                color: surfaceAlt,
                borderRadius: BorderRadius.circular(SokoniDimens.radiusField),
              ),
              child: Text(
                review.reply!,
                style: Theme.of(context).textTheme.bodySmall,
              ),
            ),
          ] else if (isOwner) ...[
            const SizedBox(height: 8),
            OutlinedButton(
              onPressed: () => _showReplySheet(context, ref),
              child: Text(l10n.reviewReply),
            ),
          ],
        ],
      ),
    );
  }

  Future<void> _showReplySheet(BuildContext context, WidgetRef ref) async {
    final l10n = AppLocalizations.of(context);
    final controller = TextEditingController();
    final reply = await showModalBottomSheet<String>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      builder: (sheetContext) => Padding(
        padding: EdgeInsets.only(
          left: SokoniDimens.space20,
          right: SokoniDimens.space20,
          top: SokoniDimens.space20,
          bottom:
              MediaQuery.viewInsetsOf(sheetContext).bottom +
              SokoniDimens.space24,
        ),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              l10n.reviewReply,
              style: Theme.of(sheetContext).textTheme.titleLarge,
            ),
            const SizedBox(height: SokoniDimens.space16),
            TextField(controller: controller, maxLines: 3, autofocus: true),
            const SizedBox(height: SokoniDimens.space20),
            FilledButton(
              onPressed: () =>
                  Navigator.of(sheetContext).pop(controller.text.trim()),
              child: Text(l10n.reviewSubmit),
            ),
          ],
        ),
      ),
    );
    if (reply == null || reply.isEmpty) return;

    try {
      await ref
          .read(reviewRepositoryProvider)
          .reply(reviewId: review.id, reply: reply);
      ref.invalidate(sellerReviewsProvider(shopHandle));
    } on ApiException catch (e) {
      if (context.mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(e.message)));
      }
    }
  }
}
