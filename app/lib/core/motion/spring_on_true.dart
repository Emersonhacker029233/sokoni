import 'package:flutter/material.dart';

/// A small spring scale bounce whenever [trigger] flips from `false` to
/// `true` — built for the Save/heart icon in an action row filling in sync
/// with the double-tap-to-save heart animation (`double_tap_to_save.dart`),
/// but generic enough for any icon that needs the same "just became true"
/// emphasis. Never bounces on `true` -> `false` or on an unrelated rebuild —
/// only that specific transition, matching `BadgeBounce`'s
/// [didUpdateWidget]-gated pattern rather than animating on every build.
class SpringOnTrue extends StatefulWidget {
  const SpringOnTrue({required this.child, required this.trigger, super.key});

  final Widget child;
  final bool trigger;

  @override
  State<SpringOnTrue> createState() => _SpringOnTrueState();
}

class _SpringOnTrueState extends State<SpringOnTrue> with SingleTickerProviderStateMixin {
  late final AnimationController _controller;
  late final Animation<double> _scale;

  @override
  void initState() {
    super.initState();
    _controller = AnimationController(vsync: this, duration: const Duration(milliseconds: 350));
    _scale = Tween<double>(begin: 1, end: 1.35).animate(
      CurvedAnimation(parent: _controller, curve: const Interval(0, 0.7, curve: Curves.elasticOut), reverseCurve: Curves.easeIn),
    );
  }

  @override
  void didUpdateWidget(covariant SpringOnTrue oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (widget.trigger && !oldWidget.trigger) {
      if (MediaQuery.disableAnimationsOf(context)) return;
      _controller.forward(from: 0).then((_) => _controller.reverse());
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
      builder: (context, _) => Transform.scale(scale: _scale.value, child: widget.child),
    );
  }
}
