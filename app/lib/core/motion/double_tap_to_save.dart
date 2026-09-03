import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../theme/colors.dart';

/// Instagram-style double-tap-to-save, for any product image/video surface.
///
/// Double-tapping anywhere on [child] plays a yellow heart over its centre
/// and calls [onSave] — never a toggle: [onSave] is expected to be
/// idempotent (always "save", never "unsave"), so double-tapping an
/// already-saved item just replays the animation rather than undoing a
/// previous tap. [onSingleTap], if given, still fires on an isolated tap
/// with no extra latency of this widget's own making: [GestureDetector]'s
/// built-in `onTap`/`onDoubleTap` pairing already uses the platform's own
/// tap-vs-double-tap disambiguation window (`kDoubleTapTimeout`, ~300ms) —
/// the same wait every correct double-tap-to-like implementation needs
/// (including Instagram's own), since there is no way to know a second tap
/// isn't coming without waiting for that window to pass. There is no
/// faster *correct* alternative that still tells the two gestures apart.
///
/// Deliberately not used on grid cards (CLAUDE.md instruction) — those
/// images are too small for a reliable double-tap target.
class DoubleTapToSave extends StatefulWidget {
  const DoubleTapToSave({required this.child, required this.onSave, this.onSingleTap, super.key});

  final Widget child;
  final VoidCallback onSave;
  final VoidCallback? onSingleTap;

  @override
  State<DoubleTapToSave> createState() => _DoubleTapToSaveState();
}

class _DoubleTapToSaveState extends State<DoubleTapToSave> with SingleTickerProviderStateMixin {
  static const _duration = Duration(milliseconds: 800);

  late final AnimationController _controller;
  late final Animation<double> _scale;
  late final Animation<double> _opacity;

  @override
  void initState() {
    super.initState();
    _controller = AnimationController(vsync: this, duration: _duration);
    // Reset to AnimationStatus.dismissed once the sequence finishes so the
    // (by-then-invisible) heart actually leaves the tree, rather than
    // sitting parked at `completed` (opacity 0, but still built) forever —
    // see build()'s dismissed check below.
    _controller.addStatusListener((status) {
      if (status == AnimationStatus.completed) _controller.reset();
    });

    // Scales 0 -> 1.2 with an elastic overshoot, settles to 1.0, then holds
    // — the hold is the flat tail on this sequence, not a separate step.
    _scale = TweenSequence<double>([
      TweenSequenceItem(tween: Tween(begin: 0.0, end: 1.2).chain(CurveTween(curve: Curves.elasticOut)), weight: 45),
      TweenSequenceItem(tween: Tween(begin: 1.2, end: 1.0).chain(CurveTween(curve: Curves.easeOut)), weight: 15),
      TweenSequenceItem(tween: ConstantTween(1.0), weight: 40),
    ]).animate(_controller);

    // Fully visible through the scale-and-hold, fades out only in the last
    // quarter of the timeline.
    _opacity = TweenSequence<double>([
      TweenSequenceItem(tween: ConstantTween(1.0), weight: 75),
      TweenSequenceItem(tween: Tween(begin: 1.0, end: 0.0).chain(CurveTween(curve: Curves.easeOut)), weight: 25),
    ]).animate(_controller);
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  void _handleDoubleTap() {
    HapticFeedback.lightImpact();
    widget.onSave();
    if (MediaQuery.disableAnimationsOf(context)) return;
    _controller.forward(from: 0);
  }

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      behavior: HitTestBehavior.opaque,
      onTap: widget.onSingleTap,
      onDoubleTap: _handleDoubleTap,
      child: Stack(
        alignment: Alignment.center,
        children: [
          widget.child,
          IgnorePointer(
            child: AnimatedBuilder(
              animation: _controller,
              builder: (context, _) {
                if (_controller.status == AnimationStatus.dismissed) return const SizedBox.shrink();
                return Opacity(
                  opacity: _opacity.value.clamp(0.0, 1.0),
                  child: Transform.scale(scale: _scale.value.clamp(0.0, 1.2), child: const _SaveHeart()),
                );
              },
            ),
          ),
        ],
      ),
    );
  }
}

/// Sokoni yellow with a soft white outline so it reads over any photograph
/// — a stacked white glow behind a smaller solid yellow glyph, the same
/// `Shadow`-on-`Icon` technique already used for legibility elsewhere in
/// this app (e.g. the mute icon over video in `product_media_carousel.dart`).
class _SaveHeart extends StatelessWidget {
  const _SaveHeart();

  @override
  Widget build(BuildContext context) {
    return const Icon(
      Icons.favorite_rounded,
      size: 96,
      color: SokoniColors.sokoniYellow,
      shadows: [
        Shadow(color: Colors.white, blurRadius: 16),
        Shadow(color: Colors.white, blurRadius: 16),
        Shadow(color: Colors.white70, blurRadius: 6),
      ],
    );
  }
}
