import 'package:flutter/material.dart';

import '../theme/colors.dart';

/// Motion primitive 6 — cart / order badge.
///
/// Wrap an icon in [BadgeBounce] and bump [count] whenever the underlying
/// value changes (e.g. items added to cart, order status updates): the
/// badge does a spring scale bounce and a single yellow dot pulses once.
/// A single [AnimationController] drives both effects; nothing animates on
/// unrelated rebuilds since the controller only fires in [didUpdateWidget]
/// when [count] actually changes.
class BadgeBounce extends StatefulWidget {
  const BadgeBounce({required this.icon, required this.count, super.key});

  final Widget icon;
  final int count;

  @override
  State<BadgeBounce> createState() => _BadgeBounceState();
}

class _BadgeBounceState extends State<BadgeBounce>
    with SingleTickerProviderStateMixin {
  late final AnimationController _controller;
  late Animation<double> _scale;
  late Animation<double> _pulse;

  @override
  void initState() {
    super.initState();
    _controller = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 400),
    );
    _buildAnimations();
  }

  void _buildAnimations() {
    _scale = Tween<double>(begin: 1, end: 1.35).animate(
      CurvedAnimation(
        parent: _controller,
        curve: const Interval(0, 0.6, curve: Curves.elasticOut),
        reverseCurve: Curves.easeIn,
      ),
    );
    _pulse = Tween<double>(begin: 1, end: 0).animate(
      CurvedAnimation(parent: _controller, curve: const Interval(0.2, 1, curve: Curves.easeOut)),
    );
  }

  @override
  void didUpdateWidget(covariant BadgeBounce oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (widget.count != oldWidget.count && widget.count > 0) {
      if (MediaQuery.disableAnimationsOf(context)) return;
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
    return AnimatedBuilder(
      animation: _controller,
      builder: (context, _) {
        return Stack(
          clipBehavior: Clip.none,
          children: [
            Transform.scale(scale: _scale.value.clamp(1, 1.35), child: widget.icon),
            if (widget.count > 0)
              Positioned(
                top: -2,
                right: -2,
                child: Stack(
                  clipBehavior: Clip.none,
                  alignment: Alignment.center,
                  children: [
                    if (_pulse.value < 1)
                      Opacity(
                        opacity: (1 - _pulse.value).clamp(0, 1),
                        child: Transform.scale(
                          scale: 1 + _pulse.value * 1.8,
                          child: Container(
                            width: 16,
                            height: 16,
                            decoration: const BoxDecoration(
                              color: SokoniColors.sokoniYellow,
                              shape: BoxShape.circle,
                            ),
                          ),
                        ),
                      ),
                    Container(
                      width: 16,
                      height: 16,
                      alignment: Alignment.center,
                      decoration: const BoxDecoration(
                        color: SokoniColors.sokoniYellow,
                        shape: BoxShape.circle,
                      ),
                      child: Text(
                        widget.count > 9 ? '9+' : '${widget.count}',
                        style: const TextStyle(
                          fontSize: 9,
                          fontWeight: FontWeight.w700,
                          color: SokoniColors.onYellow,
                          height: 1,
                        ),
                      ),
                    ),
                  ],
                ),
              ),
          ],
        );
      },
    );
  }
}
