import 'package:flutter/material.dart';

import '../../core/theme/colors.dart';

/// Yellow disc that scales in, then a checkmark drawn stroke by stroke.
/// Same success moment as the customer app's order-placed screen.
class SuccessCheck extends StatefulWidget {
  const SuccessCheck({super.key, this.size = 120});

  final double size;

  @override
  State<SuccessCheck> createState() => _SuccessCheckState();
}

class _SuccessCheckState extends State<SuccessCheck> with SingleTickerProviderStateMixin {
  late final AnimationController _c = AnimationController(vsync: this, duration: const Duration(milliseconds: 900));
  late final Animation<double> _scale = CurvedAnimation(parent: _c, curve: const Interval(0, 0.45, curve: Curves.easeOutBack));
  late final Animation<double> _draw = CurvedAnimation(parent: _c, curve: const Interval(0.35, 1, curve: Curves.easeOutCubic));

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (MediaQuery.of(context).disableAnimations) {
      _c.value = 1;
    } else if (_c.value == 0) {
      _c.forward();
    }
  }

  @override
  void dispose() {
    _c.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return AnimatedBuilder(
      animation: _c,
      builder: (context, _) => Transform.scale(
        scale: _scale.value,
        child: SizedBox.square(
          dimension: widget.size,
          child: CustomPaint(painter: _CheckPainter(progress: _draw.value)),
        ),
      ),
    );
  }
}

class _CheckPainter extends CustomPainter {
  _CheckPainter({required this.progress});

  final double progress;

  @override
  void paint(Canvas canvas, Size size) {
    final r = size.width / 2;
    canvas.drawCircle(Offset(r, r), r, Paint()..color = SokoniColors.sokoniYellow);

    final path = Path()
      ..moveTo(size.width * 0.28, size.height * 0.52)
      ..lineTo(size.width * 0.44, size.height * 0.67)
      ..lineTo(size.width * 0.73, size.height * 0.36);
    final metric = path.computeMetrics().first;
    final partial = metric.extractPath(0, metric.length * progress);
    canvas.drawPath(
      partial,
      Paint()
        ..color = SokoniColors.onYellow
        ..style = PaintingStyle.stroke
        ..strokeWidth = size.width * 0.08
        ..strokeCap = StrokeCap.round
        ..strokeJoin = StrokeJoin.round,
    );
  }

  @override
  bool shouldRepaint(_CheckPainter old) => old.progress != progress;
}
