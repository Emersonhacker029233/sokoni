import 'package:flutter/material.dart';

import '../theme/colors.dart';

/// The Updates tray's "unseen" indicator (CLAUDE.md Part 3: "Sokoni yellow
/// ring when unseen") — a circular ring around a shop's avatar/thumbnail,
/// Sokoni yellow while [seen] is false, the theme's outline colour once
/// it's true. Same skeleton as every other motion primitive here: one
/// [AnimationController], firing only in [didUpdateWidget] when [seen]
/// actually flips (never on unrelated rebuilds), honouring
/// `MediaQuery.disableAnimations`.
class StoryRing extends StatefulWidget {
  const StoryRing({required this.seen, required this.child, this.size = 64, super.key});

  final bool seen;
  final Widget child;
  final double size;

  @override
  State<StoryRing> createState() => _StoryRingState();
}

class _StoryRingState extends State<StoryRing> with SingleTickerProviderStateMixin {
  late final AnimationController _controller;
  late Animation<double> _scale;

  @override
  void initState() {
    super.initState();
    _controller = AnimationController(vsync: this, duration: const Duration(milliseconds: 320));
    _scale = Tween<double>(
      begin: 0.85,
      end: 1,
    ).animate(CurvedAnimation(parent: _controller, curve: Curves.easeOutCubic));
    if (!widget.seen) {
      _controller.value = 1;
    }
  }

  @override
  void didUpdateWidget(covariant StoryRing oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (widget.seen == oldWidget.seen) return;

    if (!widget.seen) {
      // A new Update arrived on an already-seen shop — announce it.
      if (MediaQuery.disableAnimationsOf(context)) {
        _controller.value = 1;
        return;
      }
      _controller.forward(from: 0);
    }
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final seenColor = isDark ? SokoniColors.darkOutline : SokoniColors.outline;

    return AnimatedBuilder(
      animation: _controller,
      builder: (context, _) {
        final scale = widget.seen ? 1.0 : _scale.value;
        return Transform.scale(
          scale: scale,
          child: Container(
            width: widget.size,
            height: widget.size,
            padding: const EdgeInsets.all(2.5),
            decoration: BoxDecoration(
              shape: BoxShape.circle,
              border: Border.all(
                color: widget.seen ? seenColor : SokoniColors.sokoniYellow,
                width: widget.seen ? 1 : 2,
              ),
            ),
            child: ClipOval(child: widget.child),
          ),
        );
      },
    );
  }
}
