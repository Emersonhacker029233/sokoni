import 'package:flutter/material.dart';

/// A subtle bottom-anchored gradient so overlaid icons/text stay legible
/// over an arbitrary image or video frame (CLAUDE.md Part 5) — used
/// wherever a badge, count or control sits directly on top of media
/// content rather than below it in a separate text block.
class BottomGradientScrim extends StatelessWidget {
  const BottomGradientScrim({this.heightFraction = 0.4, super.key});

  final double heightFraction;

  @override
  Widget build(BuildContext context) {
    return Align(
      alignment: Alignment.bottomCenter,
      child: FractionallySizedBox(
        heightFactor: heightFraction,
        widthFactor: 1,
        child: DecoratedBox(
          decoration: BoxDecoration(
            gradient: LinearGradient(
              begin: Alignment.topCenter,
              end: Alignment.bottomCenter,
              colors: [Colors.black.withValues(alpha: 0), Colors.black.withValues(alpha: 0.45)],
            ),
          ),
        ),
      ),
    );
  }
}
