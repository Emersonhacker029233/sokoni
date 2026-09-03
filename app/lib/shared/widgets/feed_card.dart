import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart' show HapticFeedback;
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:share_plus/share_plus.dart';
import 'package:url_launcher/url_launcher.dart';
import 'package:video_player/video_player.dart';
import 'package:visibility_detector/visibility_detector.dart';

import '../../core/l10n/gen/app_localizations.dart';
import '../../core/motion/double_tap_to_save.dart';
import '../../core/motion/spring_on_true.dart';
import '../../core/router/routes.dart';
import '../../core/theme/colors.dart';
import '../../core/theme/dimens.dart';
import '../../core/utils/formatters.dart';
import '../../data/models/feed_item.dart';
import '../../data/models/offer.dart';
import '../../data/models/product.dart';
import '../../data/models/product_media.dart';
import '../../data/models/seller_summary.dart';
import '../../data/models/showcase.dart';
import '../../features/auth/presentation/sign_in_prompt_sheet.dart';
import '../../features/discovery/presentation/widgets/comment_sheet.dart';
import '../../features/product/providers/favorites_providers.dart';
import '../../features/seller/providers/seller_providers.dart';
import 'bottom_gradient_scrim.dart';
import 'sokoni_network_image.dart';

/// One card in the "For You" feed (CLAUDE.md Part 3) — dispatches on
/// [FeedItem.type] to the right layout. All three variants share the same
/// seller-header + full-width-media + action-row skeleton so the feed
/// reads as one consistent surface, not three unrelated card designs
/// stitched together.
class FeedCard extends StatelessWidget {
  const FeedCard({required this.item, super.key});

  final FeedItem item;

  @override
  Widget build(BuildContext context) {
    return switch (item.type) {
      FeedItemType.product => _ProductFeedCard(product: item.product!),
      FeedItemType.offer => _OfferFeedCard(offer: item.offer!),
      FeedItemType.showcase => _ShowcaseFeedCard(showcase: item.showcase!),
    };
  }
}

/// Shared chrome every card variant sits inside: seller header on top,
/// media in the middle, action row + caption at the bottom. 0 gap between
/// feed cards with a 1px divider instead (CLAUDE.md Part 5).
class _FeedCardShell extends StatelessWidget {
  const _FeedCardShell({
    required this.seller,
    required this.onSellerTap,
    required this.distanceKm,
    required this.media,
    required this.footer,
    this.sponsoredContactMethod,
  });

  final SellerSummary? seller;
  final VoidCallback? onSellerTap;
  final double? distanceKm;
  final Widget media;
  final Widget footer;
  final String? sponsoredContactMethod;

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final outline = isDark ? SokoniColors.darkOutline : SokoniColors.outline;

    return Container(
      decoration: BoxDecoration(border: Border(bottom: BorderSide(color: outline))),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(
              SokoniDimens.space16,
              SokoniDimens.space12,
              SokoniDimens.space16,
              SokoniDimens.space8,
            ),
            child: _SellerHeader(seller: seller, onTap: onSellerTap, distanceKm: distanceKm),
          ),
          if (sponsoredContactMethod != null)
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: SokoniDimens.space16),
              child: _SponsoredLabel(contactMethod: sponsoredContactMethod!),
            ),
          const SizedBox(height: SokoniDimens.space8),
          AspectRatio(aspectRatio: 4 / 5, child: media),
          Padding(
            padding: const EdgeInsets.fromLTRB(
              SokoniDimens.space16,
              SokoniDimens.space8,
              SokoniDimens.space16,
              SokoniDimens.space16,
            ),
            child: footer,
          ),
        ],
      ),
    );
  }
}

class _SellerHeader extends StatelessWidget {
  const _SellerHeader({required this.seller, required this.onTap, this.distanceKm});

  final SellerSummary? seller;
  final VoidCallback? onTap;
  final double? distanceKm;

  @override
  Widget build(BuildContext context) {
    final s = seller;
    if (s == null) return const SizedBox.shrink();
    final textTheme = Theme.of(context).textTheme;

    return GestureDetector(
      onTap: onTap,
      child: Row(
        children: [
          CircleAvatar(
            radius: 18,
            backgroundColor: SokoniColors.surfaceAlt,
            backgroundImage: s.logo != null ? CachedNetworkImageProvider(s.logo!) : null,
            child: s.logo == null ? const Icon(Icons.storefront_outlined, size: 18) : null,
          ),
          const SizedBox(width: SokoniDimens.space8),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Flexible(
                      child: Text(
                        s.shopName,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: textTheme.bodyMedium?.copyWith(fontWeight: FontWeight.w600),
                      ),
                    ),
                    if (s.isVerified)
                      const Padding(
                        padding: EdgeInsets.only(left: 4),
                        child: Icon(Icons.verified_rounded, size: 14, color: SokoniColors.sokoniYellow),
                      ),
                  ],
                ),
                if (distanceKm != null)
                  Text(SokoniFormat.distanceKm(distanceKm!), style: textTheme.bodySmall),
              ],
            ),
          ),
          _FeedFollowButton(seller: s),
        ],
      ),
    );
  }
}

class _FeedFollowButton extends ConsumerWidget {
  const _FeedFollowButton({required this.seller});

  final SellerSummary seller;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = AppLocalizations.of(context);
    final following = isSellerFollowed(ref, sellerId: seller.id, isFollowing: seller.isFollowing);
    if (following) return const SizedBox.shrink();

    return TextButton(
      style: TextButton.styleFrom(
        minimumSize: const Size(44, 44),
        padding: const EdgeInsets.symmetric(horizontal: SokoniDimens.space8),
      ),
      onPressed: () => toggleSellerFollow(
        ref,
        sellerId: seller.id,
        handle: seller.handle,
        isFollowing: seller.isFollowing,
        onUnauthenticated: () => showSignInPrompt(context, message: l10n.guestPromptFollow),
        onFailed: (message) => ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(message))),
      ),
      child: Text(l10n.followAction, style: const TextStyle(fontWeight: FontWeight.w700)),
    );
  }
}

class _SponsoredLabel extends StatelessWidget {
  const _SponsoredLabel({required this.contactMethod});

  final String contactMethod;

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    return Text(
      l10n.feedSponsoredLabel,
      style: Theme.of(
        context,
      ).textTheme.bodySmall?.copyWith(color: Theme.of(context).hintColor),
    );
  }
}

/// Muted autoplay above 60% visibility (CLAUDE.md Part 3), the one place
/// in this app a scrolling (not paged) list needs `visibility_detector` —
/// every other video surface (product detail's carousel, the Showcase
/// tab's vertical PageView) has each item fully on- or off-screen, which
/// doesn't apply to a card partway scrolled into view.
class FeedVisibilityVideo extends StatefulWidget {
  const FeedVisibilityVideo({required this.videoUrl, required this.visibilityKey, this.thumbnailUrl, super.key});

  final String videoUrl;
  final String? thumbnailUrl;
  final Key visibilityKey;

  @override
  State<FeedVisibilityVideo> createState() => _FeedVisibilityVideoState();
}

class _FeedVisibilityVideoState extends State<FeedVisibilityVideo> {
  VideoPlayerController? _controller;
  bool _initializing = false;

  @override
  void dispose() {
    _controller?.dispose();
    super.dispose();
  }

  Future<void> _ensureController() async {
    if (_controller != null || _initializing || !mounted) return;
    _initializing = true;
    final controller = VideoPlayerController.networkUrl(Uri.parse(widget.videoUrl));
    await controller.initialize();
    await controller.setLooping(true);
    await controller.setVolume(0);
    if (!mounted) {
      await controller.dispose();
      return;
    }
    await controller.play();
    setState(() {
      _controller = controller;
      _initializing = false;
    });
  }

  void _onVisibilityChanged(VisibilityInfo info) {
    if (info.visibleFraction > 0.6) {
      final controller = _controller;
      if (controller == null) {
        _ensureController();
      } else {
        controller.play();
      }
    } else {
      _controller?.pause();
    }
  }

  @override
  Widget build(BuildContext context) {
    final controller = _controller;

    return VisibilityDetector(
      key: widget.visibilityKey,
      onVisibilityChanged: _onVisibilityChanged,
      child: Stack(
        fit: StackFit.expand,
        children: [
          if (widget.thumbnailUrl != null)
            SokoniNetworkImage(imageUrl: widget.thumbnailUrl!, fit: BoxFit.cover),
          if (controller != null && controller.value.isInitialized)
            FittedBox(
              fit: BoxFit.cover,
              child: SizedBox(
                width: controller.value.size.width,
                height: controller.value.size.height,
                child: VideoPlayer(controller),
              ),
            ),
          const BottomGradientScrim(heightFraction: 0.25),
          const Positioned(
            right: 12,
            bottom: 12,
            child: Icon(Icons.volume_off_rounded, color: Colors.white, shadows: [Shadow(blurRadius: 4)]),
          ),
        ],
      ),
    );
  }
}

class _ProductFeedCard extends ConsumerWidget {
  const _ProductFeedCard({required this.product});

  final Product product;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    return _FeedCardShell(
      seller: product.seller,
      onSellerTap: product.seller == null ? null : () => context.push(SokoniRoutes.shop(product.seller!.handle)),
      distanceKm: product.distanceKm,
      sponsoredContactMethod: product.isSponsored ? product.sponsorContactMethod : null,
      media: DoubleTapToSave(
        onSave: () => saveProductViaDoubleTap(
          ref,
          productId: product.id,
          knownFavorited: product.isFavorited,
          onUnauthenticated: () => showSignInPrompt(context, message: AppLocalizations.of(context).guestPromptSave),
          onFailed: (message) => ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(message))),
        ),
        onSingleTap: () => context.push(SokoniRoutes.product(product.id)),
        child: _ProductMedia(product: product),
      ),
      footer: _ProductFooter(product: product),
    );
  }
}

class _ProductMedia extends StatelessWidget {
  const _ProductMedia({required this.product});

  final Product product;

  @override
  Widget build(BuildContext context) {
    if (product.media.isEmpty) {
      return Container(
        color: SokoniColors.surfaceAlt,
        child: const Icon(Icons.image_outlined, size: 48),
      );
    }

    final first = product.media.first;
    if (first.isVideo) {
      return FeedVisibilityVideo(
        visibilityKey: ValueKey('feed-product-video-${product.id}'),
        videoUrl: first.path,
        thumbnailUrl: first.thumbPath,
      );
    }

    return PageView.builder(
      itemCount: product.media.length,
      itemBuilder: (context, index) {
        final item = product.media[index];
        if (item.isVideo) {
          return FeedVisibilityVideo(
            visibilityKey: ValueKey('feed-product-video-${product.id}-$index'),
            videoUrl: item.path,
            thumbnailUrl: item.thumbPath,
          );
        }
        return SokoniNetworkImage(
          imageUrl: item.cardPath ?? item.path,
          fit: BoxFit.cover,
          placeholder: (context, url) => Container(color: SokoniColors.surfaceAlt),
        );
      },
    );
  }
}

class _ProductFooter extends ConsumerWidget {
  const _ProductFooter({required this.product});

  final Product product;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = AppLocalizations.of(context);
    final textTheme = Theme.of(context).textTheme;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        _ActionRow(
          product: product,
          contactMethod: product.isSponsored ? product.sponsorContactMethod : null,
        ),
        const SizedBox(height: SokoniDimens.space8),
        Text(SokoniFormat.tzs(product.price), style: textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w700)),
        const SizedBox(height: SokoniDimens.space4),
        RichText(
          maxLines: 2,
          overflow: TextOverflow.ellipsis,
          text: TextSpan(
            style: textTheme.bodyMedium,
            children: [
              if (product.seller != null)
                TextSpan(
                  text: '@${product.seller!.handle} ',
                  style: const TextStyle(fontWeight: FontWeight.w700),
                ),
              TextSpan(text: product.title),
            ],
          ),
        ),
        if ((product.commentsCount ?? 0) > 0)
          Padding(
            padding: const EdgeInsets.only(top: SokoniDimens.space4),
            child: GestureDetector(
              onTap: () => showCommentSheet(context, productId: product.id),
              child: Text(
                l10n.feedViewAllComments(product.commentsCount!),
                style: textTheme.bodySmall?.copyWith(color: Theme.of(context).hintColor),
              ),
            ),
          ),
      ],
    );
  }
}

class _ActionRow extends ConsumerWidget {
  const _ActionRow({required this.product, this.contactMethod});

  final Product product;
  final String? contactMethod;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = AppLocalizations.of(context);
    final favorited = isProductFavorited(ref, productId: product.id, knownFavorited: product.isFavorited);

    return Row(
      children: [
        IconButton(
          constraints: const BoxConstraints(minWidth: 44, minHeight: 44),
          icon: SpringOnTrue(
            trigger: favorited,
            child: Icon(
              favorited ? Icons.favorite_rounded : Icons.favorite_border_rounded,
              color: favorited ? SokoniColors.danger : null,
            ),
          ),
          onPressed: () => toggleProductFavorite(
            ref,
            productId: product.id,
            knownFavorited: product.isFavorited,
            onUnauthenticated: () => showSignInPrompt(context, message: l10n.guestPromptSave),
            onFailed: (message) => ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(message))),
          ),
        ),
        IconButton(
          constraints: const BoxConstraints(minWidth: 44, minHeight: 44),
          icon: const Icon(Icons.mode_comment_outlined),
          onPressed: () => showCommentSheet(context, productId: product.id),
        ),
        IconButton(
          constraints: const BoxConstraints(minWidth: 44, minHeight: 44),
          icon: const Icon(Icons.ios_share_rounded),
          onPressed: () => SharePlus.instance.share(
            ShareParams(
              text: '${product.title} — ${SokoniFormat.tzs(product.price)}\n'
                  'https://sokoni.co.tz/products/${product.id}',
            ),
          ),
        ),
        _ChatButton(product: product, contactMethod: contactMethod),
        const Spacer(),
        FilledButton(
          onPressed: () {
            HapticFeedback.mediumImpact();
            context.push(SokoniRoutes.product(product.id));
          },
          style: FilledButton.styleFrom(
            padding: const EdgeInsets.symmetric(horizontal: SokoniDimens.space16),
            minimumSize: const Size(0, 44),
          ),
          child: Text(l10n.feedOrderAction),
        ),
      ],
    );
  }
}

/// A sponsored card's single chosen contact action (CLAUDE.md Part 3: "the
/// seller's chosen contact preference: in-app Chat, WhatsApp, or Call")
/// replaces the default Chat icon; an un-sponsored card just gets the
/// default in-app Chat action.
class _ChatButton extends ConsumerWidget {
  const _ChatButton({required this.product, this.contactMethod});

  final Product product;
  final String? contactMethod;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final seller = product.seller;
    if (seller == null) return const SizedBox.shrink();

    switch (contactMethod) {
      case 'whatsapp':
        return IconButton(
          constraints: const BoxConstraints(minWidth: 44, minHeight: 44),
          icon: const Icon(Icons.phone_in_talk_outlined),
          onPressed: seller.whatsapp == null
              ? null
              : () => launchUrl(
                  Uri.parse(
                    'https://wa.me/${seller.whatsapp!.replaceAll('+', '')}'
                    '?text=${Uri.encodeComponent(product.title)}',
                  ),
                  mode: LaunchMode.externalApplication,
                ),
        );
      case 'call':
        return IconButton(
          constraints: const BoxConstraints(minWidth: 44, minHeight: 44),
          icon: const Icon(Icons.call_outlined),
          onPressed: seller.whatsapp == null ? null : () => launchUrl(Uri.parse('tel:${seller.whatsapp}')),
        );
      default:
        return IconButton(
          constraints: const BoxConstraints(minWidth: 44, minHeight: 44),
          icon: const Icon(Icons.chat_bubble_outline_rounded),
          onPressed: () async {
            final l10n = AppLocalizations.of(context);
            final conversationId = await startConversationOrPromptSignIn(
              ref,
              sellerId: seller.id,
              productId: product.id,
              onUnauthenticated: () {
                if (context.mounted) showSignInPrompt(context, message: l10n.guestPromptMessage);
              },
              onFailed: (message) {
                if (context.mounted) {
                  ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(message)));
                }
              },
            );
            if (conversationId != null && context.mounted) {
              await context.push(SokoniRoutes.conversation(conversationId));
            }
          },
        );
    }
  }
}

class _OfferFeedCard extends StatelessWidget {
  const _OfferFeedCard({required this.offer});

  final Offer offer;

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    final product = offer.product;
    final remaining = offer.timeRemaining;
    final countdown = remaining.inHours >= 24
        ? l10n.offerCountdownDays(remaining.inDays)
        : l10n.offerCountdownHours(remaining.inHours.clamp(0, 23));

    return _FeedCardShell(
      seller: offer.seller,
      onSellerTap: offer.seller == null ? null : () => context.push(SokoniRoutes.shop(offer.seller!.handle)),
      distanceKm: null,
      media: product?.coverImageUrl != null
          ? SokoniNetworkImage(imageUrl: product!.coverImageUrl!, fit: BoxFit.cover)
          : Container(color: SokoniColors.surfaceAlt, child: const Icon(Icons.local_offer_outlined, size: 48)),
      footer: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            padding: const EdgeInsets.symmetric(horizontal: SokoniDimens.space8, vertical: 2),
            decoration: BoxDecoration(
              color: SokoniColors.sokoniYellow,
              borderRadius: BorderRadius.circular(SokoniDimens.radiusChip),
            ),
            child: Text(
              countdown,
              style: const TextStyle(color: SokoniColors.onYellow, fontSize: 12, fontWeight: FontWeight.w700),
            ),
          ),
          const SizedBox(height: SokoniDimens.space8),
          Row(
            crossAxisAlignment: CrossAxisAlignment.end,
            children: [
              Text(
                SokoniFormat.tzs(offer.priceSnapshot.round()),
                style: const TextStyle(decoration: TextDecoration.lineThrough, color: SokoniColors.danger),
              ),
              const SizedBox(width: SokoniDimens.space8),
              Text(
                SokoniFormat.tzs(offer.discountedPrice),
                style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w700),
              ),
            ],
          ),
          const SizedBox(height: SokoniDimens.space4),
          Text(product?.title ?? '', maxLines: 2, overflow: TextOverflow.ellipsis),
          const SizedBox(height: SokoniDimens.space8),
          if (product != null)
            FilledButton(
              onPressed: () {
                HapticFeedback.mediumImpact();
                context.push(SokoniRoutes.product(product.id));
              },
              style: FilledButton.styleFrom(minimumSize: const Size(0, 44)),
              child: Text(l10n.feedOrderAction),
            ),
        ],
      ),
    );
  }
}

class _ShowcaseFeedCard extends StatelessWidget {
  const _ShowcaseFeedCard({required this.showcase});

  final Showcase showcase;

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: () => context.push(SokoniRoutes.showcaseFeed),
      child: _FeedCardShell(
        seller: showcase.seller,
        onSellerTap: showcase.seller == null ? null : () => context.push(SokoniRoutes.shop(showcase.seller!.handle)),
        distanceKm: null,
        media: FeedVisibilityVideo(
          visibilityKey: ValueKey('feed-showcase-video-${showcase.id}'),
          videoUrl: showcase.videoPath,
          thumbnailUrl: showcase.thumbPath,
        ),
        footer: Text(showcase.caption ?? showcase.product?.title ?? '', maxLines: 2, overflow: TextOverflow.ellipsis),
      ),
    );
  }
}
