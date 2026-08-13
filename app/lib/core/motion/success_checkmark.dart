import 'dart:async';

import 'package:flutter/material.dart';

import '../theme/colors.dart';

/// Motion primitive 8 — success moments.
///
/// A full-screen overlay with a hand-drawn checkmark (path-drawn via
/// [CustomPainter], then a scale-in of the surrounding circle) used for
/// "order placed" and "seller verified" moments. Auto-dismisses at 1.6s
/// unless the caller awaits [showSokoniSuccess] and reacts sooner.
Future<void> showSokoniSuccess(
  BuildContext context, {
  required String message,
  Duration autoDismiss = const Duration(milliseconds: 1600),
}) {
  return showGeneralDialog<void>(
    context: context,
    barrierDismissible: false,
    barrierColor: Colors.black.withValues(alpha: 0.55),
    transitionDuration: const Duration(milliseconds: 250),
    pageBuilder: (context, animation, secondaryAnimation) {
      return _SuccessMoment(message: message, autoDismiss: autoDismiss);
    },
    transitionBuilder: (context, animation, secondaryAnimation, child) {
      return FadeTransition(
        opacity: CurvedAnimation(parent: animation, curve: Curves.easeOut),
        child: child,
      );
    },
  );
}

class _SuccessMoment extends StatefulWidget {
  const _SuccessMoment({required this.message, required this.autoDismiss});

  final String message;
  final Duration autoDismiss;

  @override
  State<_SuccessMoment> createState() => _SuccessMomentState();
}

class _SuccessMomentState extends State<_SuccessMoment>
    with SingleTickerProviderStateMixin {
  late final AnimationController _controller;
  Timer? _dismissTimer;
  bool _started = false;

  @override
  void initState() {
    super.initState();
    _controller = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 700),
    );
    _dismissTimer = Timer(widget.autoDismiss, () {
      if (mounted) Navigator.of(context).maybePop();
    });
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
    _dismissTimer?.cancel();
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final circleScale = CurvedAnimation(
      parent: _controller,
      curve: const Interval(0, 0.5, curve: Curves.elasticOut),
    );
    final checkProgress = CurvedAnimation(
      parent: _controller,
      curve: const Interval(0.35, 0.85, curve: Curves.easeOutCubic),
    );
    final textOpacity = CurvedAnimation(
      parent: _controller,
      curve: const Interval(0.6, 1, curve: Curves.easeOut),
    );

    return Material(
      type: MaterialType.transparency,
      child: Center(
        child: AnimatedBuilder(
          animation: _controller,
          builder: (context, _) {
            return Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                Transform.scale(
                  scale: circleScale.value,
                  child: SizedBox(
                    width: 96,
                    height: 96,
                    child: CustomPaint(
                      painter: _CheckmarkPainter(progress: checkProgress.value),
                    ),
                  ),
                ),
                const SizedBox(height: 20),
                Opacity(
                  opacity: textOpacity.value,
                  child: Text(
                    widget.message,
                    textAlign: TextAlign.center,
                    style: const TextStyle(
                      color: Colors.white,
                      fontSize: 16,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                ),
              ],
            );
          },
        ),
      ),
    );
  }
}

class _CheckmarkPainter extends CustomPainter {
  _CheckmarkPainter({required this.progress});

  /// 0..1 — 0..0.6 draws the circle stroke, 0.6..1 draws the check stroke.
  final double progress;

  @override
  void paint(Canvas canvas, Size size) {
    final center = size.center(Offset.zero);
    final radius = size.width / 2 - 4;

    final circlePaint = Paint()
      ..color = SokoniColors.sokoniYellow
      ..style = PaintingStyle.stroke
      ..strokeWidth = 4
      ..strokeCap = StrokeCap.round;

    final circleProgress = (progress / 0.6).clamp(0.0, 1.0);
    canvas.drawArc(
      Rect.fromCircle(center: center, radius: radius),
      -3.14159 / 2,
      circleProgress * 3.14159 * 2,
      false,
      circlePaint,
    );

    if (progress <= 0.6) return;
    final checkProgress = ((progress - 0.6) / 0.4).clamp(0.0, 1.0);

    final checkPath = Path();
    final p1 = Offset(center.dx - radius * 0.45, center.dy);
    final p2 = Offset(center.dx - radius * 0.12, center.dy + radius * 0.35);
    final p3 = Offset(center.dx + radius * 0.5, center.dy - radius * 0.35);
    checkPath.moveTo(p1.dx, p1.dy);
    checkPath.lineTo(p2.dx, p2.dy);
    checkPath.lineTo(p3.dx, p3.dy);

    final metrics = checkPath.computeMetrics().first;
    final extractPath = metrics.extractPath(0, metrics.length * checkProgress);

    final checkPaint = Paint()
      ..color = SokoniColors.sokoniYellow
      ..style = PaintingStyle.stroke
      ..strokeWidth = 5
      ..strokeCap = StrokeCap.round
      ..strokeJoin = StrokeJoin.round;

    canvas.drawPath(extractPath, checkPaint);
  }

  @override
  bool shouldRepaint(covariant _CheckmarkPainter oldDelegate) =>
      oldDelegate.progress != progress;
}
