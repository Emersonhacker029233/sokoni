import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:video_player/video_player.dart';

import '../../../core/l10n/gen/app_localizations.dart';
import '../../../core/router/routes.dart';
import '../../../core/theme/dimens.dart';
import '../../../data/models/social_update.dart';
import '../providers/social_providers.dart';

const _imageDuration = Duration(seconds: 5);

/// Full-screen viewer for one shop's Updates (CLAUDE.md Part 3), opened by
/// tapping its ring in the tray. Auto-advances (images on a fixed timer,
/// video on completion), tap right/left to skip, tap-and-hold-free swipe
/// down to dismiss. Marks the shop seen the moment it opens.
class StoryViewerScreen extends ConsumerStatefulWidget {
  const StoryViewerScreen({required this.updates, required this.startIndex, super.key});

  final List<SocialUpdate> updates;
  final int startIndex;

  @override
  ConsumerState<StoryViewerScreen> createState() => _StoryViewerScreenState();
}

class _StoryViewerScreenState extends ConsumerState<StoryViewerScreen> with SingleTickerProviderStateMixin {
  late final AnimationController _progress;
  late int _index;
  VideoPlayerController? _videoController;

  @override
  void initState() {
    super.initState();
    _index = widget.startIndex;
    _progress = AnimationController(vsync: this)..addStatusListener(_onProgressStatus);
    final seller = widget.updates.first.seller;
    if (seller != null) ref.read(updateSeenMarksProvider.notifier).markSeen(seller.id);
    _loadCurrent();
  }

  void _onProgressStatus(AnimationStatus status) {
    if (status == AnimationStatus.completed) _advance();
  }

  Future<void> _loadCurrent() async {
    await _videoController?.dispose();
    _videoController = null;
    _progress
      ..stop()
      ..reset();

    final update = widget.updates[_index];
    if (update.isVideo) {
      final controller = VideoPlayerController.networkUrl(Uri.parse(update.mediaPath));
      await controller.initialize();
      if (!mounted) {
        await controller.dispose();
        return;
      }
      setState(() => _videoController = controller);
      _progress.duration = controller.value.duration;
      await controller.play();
    } else {
      _progress.duration = _imageDuration;
    }
    if (mounted) _progress.forward();
  }

  void _advance() {
    if (_index >= widget.updates.length - 1) {
      Navigator.of(context).pop();
      return;
    }
    setState(() => _index++);
    _loadCurrent();
  }

  void _rewind() {
    if (_index == 0) {
      _progress
        ..reset()
        ..forward();
      return;
    }
    setState(() => _index--);
    _loadCurrent();
  }

  @override
  void dispose() {
    _progress.dispose();
    _videoController?.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    final update = widget.updates[_index];
    final seller = update.seller;

    return GestureDetector(
      onVerticalDragEnd: (details) {
        if ((details.primaryVelocity ?? 0) > 200) Navigator.of(context).pop();
      },
      child: Scaffold(
        backgroundColor: Colors.black,
        body: Stack(
          fit: StackFit.expand,
          children: [
            Row(
              children: [
                Expanded(child: GestureDetector(onTap: _rewind, behavior: HitTestBehavior.translucent)),
                Expanded(child: GestureDetector(onTap: _advance, behavior: HitTestBehavior.translucent)),
              ],
            ),
            if (update.isVideo && _videoController != null)
              FittedBox(
                fit: BoxFit.cover,
                child: SizedBox(
                  width: _videoController!.value.size.width,
                  height: _videoController!.value.size.height,
                  child: VideoPlayer(_videoController!),
                ),
              )
            else
              CachedNetworkImage(imageUrl: update.mediaPath, fit: BoxFit.cover, width: double.infinity, height: double.infinity),
            SafeArea(
              child: Padding(
                padding: const EdgeInsets.symmetric(horizontal: SokoniDimens.space12),
                child: Column(
                  children: [
                    Row(
                      children: [
                        for (var i = 0; i < widget.updates.length; i++)
                          Expanded(
                            child: Padding(
                              padding: const EdgeInsets.symmetric(horizontal: 2),
                              child: AnimatedBuilder(
                                animation: _progress,
                                builder: (context, _) => LinearProgressIndicator(
                                  value: i < _index ? 1 : (i == _index ? _progress.value : 0),
                                  backgroundColor: Colors.white24,
                                  color: Colors.white,
                                  minHeight: 2,
                                ),
                              ),
                            ),
                          ),
                      ],
                    ),
                    const SizedBox(height: SokoniDimens.space8),
                    Row(
                      children: [
                        if (seller != null)
                          Expanded(
                            child: Text(
                              seller.shopName,
                              style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w600),
                            ),
                          ),
                        IconButton(
                          icon: const Icon(Icons.close_rounded, color: Colors.white),
                          onPressed: () => Navigator.of(context).pop(),
                        ),
                      ],
                    ),
                    if (update.caption != null)
                      Align(
                        alignment: Alignment.centerLeft,
                        child: Text(update.caption!, style: const TextStyle(color: Colors.white)),
                      ),
                  ],
                ),
              ),
            ),
            if (update.product != null)
              Positioned(
                left: SokoniDimens.space16,
                right: SokoniDimens.space16,
                bottom: SokoniDimens.space24,
                child: SafeArea(
                  top: false,
                  child: FilledButton(
                    onPressed: () => context.push(SokoniRoutes.product(update.product!.id)),
                    child: Text(l10n.showcaseOrderAction),
                  ),
                ),
              ),
          ],
        ),
      ),
    );
  }
}
