import 'package:flutter/material.dart';

/// Motion primitive 3 — staggered list reveal.
///
/// Wraps a list of [children] so each one fades in and rises from 16dp
/// below its resting position, staggered 40ms apart, capped at the first
/// 8 items (items beyond that appear immediately with the 8th's delay so
/// long lists don't feel sluggish to populate).
class StaggeredReveal extends StatefulWidget {
  const StaggeredReveal({
    required this.children,
    this.axis = Axis.vertical,
    super.key,
  });

  final List<Widget> children;
  final Axis axis;

  static const Duration itemDuration = Duration(milliseconds: 350);
  static const Duration stagger = Duration(milliseconds: 40);
  static const int maxStaggeredItems = 8;

  @override
  State<StaggeredReveal> createState() => _StaggeredRevealState();
}

class _StaggeredRevealState extends State<StaggeredReveal>
    with SingleTickerProviderStateMixin {
  late final AnimationController _controller;
  bool _started = false;

  @override
  void initState() {
    super.initState();
    final staggeredCount = widget.children.length.clamp(
      0,
      StaggeredReveal.maxStaggeredItems,
    );
    final totalDuration =
        StaggeredReveal.itemDuration +
        StaggeredReveal.stagger * staggeredCount;
    _controller = AnimationController(vsync: this, duration: totalDuration);
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (_started) return;
    _started = true;
    if (MediaQuery.disableAnimationsOf(context)) {
      _controller.value = 1;
    } else {
      _controller.forward();
    }
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  Animation<double> _delayedCurve(int index) {
    final staggerIndex = index.clamp(0, StaggeredReveal.maxStaggeredItems - 1);
    final totalMs = _controller.duration!.inMilliseconds;
    final startMs = StaggeredReveal.stagger.inMilliseconds * staggerIndex;
    final endMs = startMs + StaggeredReveal.itemDuration.inMilliseconds;
    return CurvedAnimation(
      parent: _controller,
      curve: Interval(
        startMs / totalMs,
        (endMs / totalMs).clamp(0.0, 1.0),
        curve: Curves.easeOutCubic,
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return AnimatedBuilder(
      animation: _controller,
      builder: (context, _) {
        final items = <Widget>[];
        for (var i = 0; i < widget.children.length; i++) {
          final anim = _delayedCurve(i);
          items.add(
            Opacity(
              opacity: anim.value,
              child: Transform.translate(
                offset: Offset(0, (1 - anim.value) * 16),
                child: widget.children[i],
              ),
            ),
          );
        }
        return widget.axis == Axis.vertical
            ? Column(children: items)
            : Row(children: items);
      },
    );
  }
}
