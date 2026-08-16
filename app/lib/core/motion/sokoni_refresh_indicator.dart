import 'package:flutter/material.dart';

/// Motion primitive 10 — pull-to-refresh.
///
/// A ground-up replacement for [RefreshIndicator]: rather than the stock
/// Material spinner, an overscroll at the top of [child] reveals the
/// Sokoni bag mark, which rotates proportionally to pull distance while
/// dragging and spins continuously once released past the trigger
/// threshold, until [onRefresh] completes.
class SokoniRefreshIndicator extends StatefulWidget {
  const SokoniRefreshIndicator({
    required this.onRefresh,
    required this.child,
    this.triggerDistance = 80,
    super.key,
  });

  final Future<void> Function() onRefresh;
  final Widget child;
  final double triggerDistance;

  @override
  State<SokoniRefreshIndicator> createState() => _SokoniRefreshIndicatorState();
}

enum _RefreshPhase { idle, dragging, armed, refreshing }

class _SokoniRefreshIndicatorState extends State<SokoniRefreshIndicator>
    with SingleTickerProviderStateMixin {
  late final AnimationController _spinController;
  double _dragOffset = 0;
  _RefreshPhase _phase = _RefreshPhase.idle;

  @override
  void initState() {
    super.initState();
    _spinController = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 900),
    );
  }

  @override
  void dispose() {
    _spinController.dispose();
    super.dispose();
  }

  bool _onNotification(ScrollNotification notification) {
    if (notification is OverscrollNotification && notification.overscroll < 0) {
      setState(() {
        _dragOffset = (_dragOffset - notification.overscroll).clamp(0.0, widget.triggerDistance * 1.6);
        _phase = _dragOffset >= widget.triggerDistance
            ? _RefreshPhase.armed
            : _RefreshPhase.dragging;
      });
    } else if (notification is ScrollEndNotification) {
      if (_phase == _RefreshPhase.armed) {
        _startRefresh();
      } else if (_phase == _RefreshPhase.dragging) {
        setState(() {
          _dragOffset = 0;
          _phase = _RefreshPhase.idle;
        });
      }
    }
    return false;
  }

  Future<void> _startRefresh() async {
    setState(() {
      _phase = _RefreshPhase.refreshing;
      _dragOffset = widget.triggerDistance;
    });
    _spinController.repeat();
    try {
      await widget.onRefresh();
    } finally {
      if (mounted) {
        _spinController.stop();
        setState(() {
          _phase = _RefreshPhase.idle;
          _dragOffset = 0;
        });
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final pullProgress = (_dragOffset / widget.triggerDistance).clamp(0.0, 1.0);
    return Stack(
      children: [
        NotificationListener<ScrollNotification>(
          onNotification: _onNotification,
          child: widget.child,
        ),
        IgnorePointer(
          child: AnimatedOpacity(
            duration: const Duration(milliseconds: 150),
            opacity: _dragOffset > 4 ? 1 : 0,
            child: Align(
              alignment: Alignment.topCenter,
              child: Padding(
                padding: EdgeInsets.only(top: (_dragOffset - 36).clamp(4.0, widget.triggerDistance)),
                child: _phase == _RefreshPhase.refreshing
                    ? const SokoniBagSpinner(spinning: true)
                    : Transform.rotate(
                        angle: pullProgress * 6.28318,
                        child: SokoniBagSpinner(
                          size: 20 + pullProgress * 8,
                        ),
                      ),
              ),
            ),
          ),
        ),
      ],
    );
  }
}

/// The bag-mark spinner itself, reusable outside of [SokoniRefreshIndicator]
/// (e.g. as a standalone loading state).
class SokoniBagSpinner extends StatefulWidget {
  const SokoniBagSpinner({this.size = 28, this.spinning = false, super.key});

  final double size;
  final bool spinning;

  @override
  State<SokoniBagSpinner> createState() => _SokoniBagSpinnerState();
}

class _SokoniBagSpinnerState extends State<SokoniBagSpinner>
    with SingleTickerProviderStateMixin {
  late final AnimationController _controller;

  @override
  void initState() {
    super.initState();
    _controller = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 900),
    );
    if (widget.spinning) _controller.repeat();
  }

  @override
  void didUpdateWidget(covariant SokoniBagSpinner oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (widget.spinning != oldWidget.spinning) {
      if (widget.spinning) {
        _controller.repeat();
      } else {
        _controller.stop();
      }
    }
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return RotationTransition(
      turns: _controller,
      child: ClipOval(
        child: Image.asset(
          'assets/brand/sokoni_logo_icon.png',
          width: widget.size,
          height: widget.size,
          fit: BoxFit.cover,
          // Fixed regardless of widget.size (which varies continuously
          // during the pull gesture) so every frame reuses one decoded
          // image instead of re-decoding at a slightly different target
          // each time — see splash_transition.dart for why a cache hint
          // matters here at all.
          cacheWidth: 96,
          cacheHeight: 96,
        ),
      ),
    );
  }
}
