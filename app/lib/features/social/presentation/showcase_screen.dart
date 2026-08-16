import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:video_player/video_player.dart';

import '../../../core/l10n/gen/app_localizations.dart';
import '../../../core/router/routes.dart';
import '../../../core/theme/colors.dart';
import '../../../core/theme/dimens.dart';
import '../../../core/utils/formatters.dart';
import '../../../data/models/showcase.dart';
import '../../../shared/widgets/empty_state.dart';
import '../../../shared/widgets/error_state.dart';
import '../providers/social_providers.dart';

/// "Showcase"/"Onyesho" (CLAUDE.md Part 3) — a full-screen vertical
/// `PageView` of product videos, Reels/TikTok-style. Every page is either
/// fully on- or fully off-screen (a `PageView`, not a scroll feed), so the
/// video-controller lifecycle reuses `ProductMediaCarousel`'s active-index
/// pattern rather than `visibility_detector` — see that file's own
/// docblock for why that's the right call for page-based (as opposed to
/// scroll-feed) video.
class ShowcaseScreen extends ConsumerStatefulWidget {
  const ShowcaseScreen({super.key});

  @override
  ConsumerState<ShowcaseScreen> createState() => _ShowcaseScreenState();
}

class _ShowcaseScreenState extends ConsumerState<ShowcaseScreen> {
  late final PageController _pageController = PageController();
  int _activeIndex = 0;

  @override
  void dispose() {
    _pageController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    final feedAsync = ref.watch(showcaseFeedProvider);

    return Scaffold(
      backgroundColor: Colors.black,
      appBar: AppBar(
        backgroundColor: Colors.transparent,
        elevation: 0,
        foregroundColor: Colors.white,
        title: Text(l10n.showcaseRowTitle),
      ),
      extendBodyBehindAppBar: true,
      body: feedAsync.when(
        loading: () => const Center(child: CircularProgressIndicator(color: Colors.white)),
        error: (error, _) => SokoniErrorState(
          message: '$error',
          onRetry: () => ref.invalidate(showcaseFeedProvider),
        ),
        data: (state) {
          if (state.items.isEmpty) {
            return SokoniEmptyState(
              icon: Icons.video_collection_outlined,
              title: l10n.showcaseEmptyTitle,
              message: l10n.showcaseEmptyMessage,
            );
          }
          return PageView.builder(
            controller: _pageController,
            scrollDirection: Axis.vertical,
            itemCount: state.items.length,
            onPageChanged: (index) {
              setState(() => _activeIndex = index);
              // Preload one page ahead (CLAUDE.md: "preload one ahead").
              if (index >= state.items.length - 2) {
                ref.read(showcaseFeedProvider.notifier).loadMore();
              }
            },
            itemBuilder: (context, index) {
              return _ShowcasePage(showcase: state.items[index], isActive: index == _activeIndex);
            },
          );
        },
      ),
    );
  }
}

class _ShowcasePage extends StatefulWidget {
  const _ShowcasePage({required this.showcase, required this.isActive});

  final Showcase showcase;
  final bool isActive;

  @override
  State<_ShowcasePage> createState() => _ShowcasePageState();
}

class _ShowcasePageState extends State<_ShowcasePage> {
  VideoPlayerController? _controller;
  bool _muted = true;

  @override
  void initState() {
    super.initState();
    _init();
  }

  Future<void> _init() async {
    final controller = VideoPlayerController.networkUrl(Uri.parse(widget.showcase.videoPath));
    await controller.initialize();
    await controller.setLooping(true);
    await controller.setVolume(0);
    // Aggressive-disposal guard: a fast vertical swipe can dispose this
    // page before the network init resolves — see ProductMediaCarousel's
    // identical guard.
    if (!mounted) {
      await controller.dispose();
      return;
    }
    setState(() => _controller = controller);
    if (widget.isActive) await controller.play();
  }

  @override
  void didUpdateWidget(covariant _ShowcasePage oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (widget.isActive != oldWidget.isActive && _controller != null) {
      widget.isActive ? _controller!.play() : _controller!.pause();
    }
  }

  @override
  void dispose() {
    _controller?.dispose();
    super.dispose();
  }

  void _toggleMute() {
    setState(() => _muted = !_muted);
    _controller?.setVolume(_muted ? 0 : 1);
  }

  @override
  Widget build(BuildContext context) {
    final showcase = widget.showcase;
    final controller = _controller;

    return GestureDetector(
      onTap: _toggleMute,
      child: Stack(
        fit: StackFit.expand,
        children: [
          if (controller != null && controller.value.isInitialized)
            FittedBox(
              fit: BoxFit.cover,
              child: SizedBox(
                width: controller.value.size.width,
                height: controller.value.size.height,
                child: VideoPlayer(controller),
              ),
            )
          else
            CachedNetworkImage(imageUrl: showcase.thumbPath, fit: BoxFit.cover),
          Positioned(
            left: 0,
            right: 0,
            bottom: 0,
            child: Container(
              padding: const EdgeInsets.fromLTRB(
                SokoniDimens.space16,
                SokoniDimens.space32,
                SokoniDimens.space16,
                SokoniDimens.space32,
              ),
              decoration: const BoxDecoration(
                gradient: LinearGradient(
                  begin: Alignment.topCenter,
                  end: Alignment.bottomCenter,
                  colors: [Colors.transparent, Colors.black87],
                ),
              ),
              child: _ProductOverlay(showcase: showcase),
            ),
          ),
        ],
      ),
    );
  }
}

class _ProductOverlay extends StatelessWidget {
  const _ProductOverlay({required this.showcase});

  final Showcase showcase;

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    final product = showcase.product;
    final seller = showcase.seller;

    return Row(
      crossAxisAlignment: CrossAxisAlignment.end,
      children: [
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            mainAxisSize: MainAxisSize.min,
            children: [
              if (seller != null)
                Text(
                  seller.shopName,
                  style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w600),
                ),
              if (product != null) ...[
                const SizedBox(height: SokoniDimens.space4),
                Text(product.title, style: const TextStyle(color: Colors.white), maxLines: 1, overflow: TextOverflow.ellipsis),
                Text(
                  SokoniFormat.tzs(product.price),
                  style: const TextStyle(color: SokoniColors.sokoniYellow, fontWeight: FontWeight.w700),
                ),
              ],
              if (showcase.caption != null) ...[
                const SizedBox(height: SokoniDimens.space4),
                Text(showcase.caption!, style: const TextStyle(color: Colors.white70), maxLines: 2, overflow: TextOverflow.ellipsis),
              ],
            ],
          ),
        ),
        if (product != null) ...[
          const SizedBox(width: SokoniDimens.space12),
          FilledButton(
            onPressed: () => context.push(SokoniRoutes.product(product.id)),
            child: Text(l10n.showcaseOrderAction),
          ),
        ],
      ],
    );
  }
}
