import 'package:flutter/material.dart';
import 'package:video_player/video_player.dart';

import '../../../../core/motion/double_tap_to_save.dart';
import '../../../../core/motion/hero_image_transition.dart';
import '../../../../core/motion/parallax_carousel.dart';
import '../../../../core/theme/colors.dart';
import '../../../../data/models/product_media.dart';
import '../../../../shared/widgets/sokoni_network_image.dart';

/// Mixed image/video carousel for the product detail screen (CLAUDE.md
/// feature 7). Video autoplays muted only while it's the active page —
/// paused otherwise — driven by the carousel's own page-change callback
/// rather than a separate visibility check, since every page here is
/// already either fully on- or off-screen (unlike a feed card).
///
/// [onDoubleTapSave] wires the Instagram-style double-tap-to-save gesture
/// (`DoubleTapToSave`) onto every page — image or video — with the video
/// pages' existing single-tap-to-mute preserved untouched as the isolated
/// single tap.
class ProductMediaCarousel extends StatefulWidget {
  const ProductMediaCarousel({required this.media, required this.heroTag, required this.onDoubleTapSave, super.key});

  final List<ProductMediaItem> media;
  final Object heroTag;
  final VoidCallback onDoubleTapSave;

  @override
  State<ProductMediaCarousel> createState() => _ProductMediaCarouselState();
}

class _ProductMediaCarouselState extends State<ProductMediaCarousel> {
  int _activeIndex = 0;

  @override
  Widget build(BuildContext context) {
    if (widget.media.isEmpty) {
      return AspectRatio(
        aspectRatio: 1,
        child: Container(
          color: SokoniColors.surfaceAlt,
          child: const Icon(Icons.image_outlined, size: 48),
        ),
      );
    }

    return SokoniHeroImage(
      tag: widget.heroTag,
      borderRadius: 0,
      child: ParallaxCarousel(
        height: 340,
        itemCount: widget.media.length,
        onPageChanged: (index) => setState(() => _activeIndex = index),
        itemBuilder: (context, index, parallax) {
          final item = widget.media[index];
          if (item.isVideo) {
            return _VideoPage(item: item, isActive: index == _activeIndex, onDoubleTapSave: widget.onDoubleTapSave);
          }
          return DoubleTapToSave(
            onSave: widget.onDoubleTapSave,
            child: ParallaxImage(
              parallax: parallax,
              image: SokoniNetworkImage(
                imageUrl: item.path,
                fit: BoxFit.cover,
                placeholder: (context, url) => Container(color: SokoniColors.surfaceAlt),
              ),
            ),
          );
        },
      ),
    );
  }
}

class _VideoPage extends StatefulWidget {
  const _VideoPage({required this.item, required this.isActive, required this.onDoubleTapSave});

  final ProductMediaItem item;
  final bool isActive;
  final VoidCallback onDoubleTapSave;

  @override
  State<_VideoPage> createState() => _VideoPageState();
}

class _VideoPageState extends State<_VideoPage> {
  VideoPlayerController? _controller;
  bool _muted = true;

  @override
  void initState() {
    super.initState();
    _init();
  }

  Future<void> _init() async {
    final controller = VideoPlayerController.networkUrl(Uri.parse(widget.item.path));
    await controller.initialize();
    await controller.setLooping(true);
    await controller.setVolume(0);
    if (!mounted) {
      await controller.dispose();
      return;
    }
    setState(() => _controller = controller);
    if (widget.isActive) await controller.play();
  }

  @override
  void didUpdateWidget(covariant _VideoPage oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (widget.isActive != oldWidget.isActive && _controller != null) {
      if (widget.isActive) {
        _controller!.play();
      } else {
        _controller!.pause();
      }
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
    final controller = _controller;
    if (controller == null || !controller.value.isInitialized) {
      return Container(
        color: SokoniColors.sokoniBlack,
        child: const Center(child: CircularProgressIndicator(color: SokoniColors.sokoniYellow)),
      );
    }

    return DoubleTapToSave(
      onSave: widget.onDoubleTapSave,
      onSingleTap: _toggleMute,
      child: Stack(
        fit: StackFit.expand,
        children: [
          FittedBox(
            fit: BoxFit.cover,
            child: SizedBox(
              width: controller.value.size.width,
              height: controller.value.size.height,
              child: VideoPlayer(controller),
            ),
          ),
          Positioned(
            right: 12,
            bottom: 12,
            child: Icon(
              _muted ? Icons.volume_off_rounded : Icons.volume_up_rounded,
              color: Colors.white,
              shadows: const [Shadow(blurRadius: 4)],
            ),
          ),
        ],
      ),
    );
  }
}
