import 'package:flutter/material.dart';

/// Motion primitive 2 — hero product images.
///
/// Wraps [child] in a [Hero] whose flight uses a [RectTween] combined with
/// an animated [ClipRRect] radius, so the corner radius interpolates
/// smoothly between the card's rounded corners and the detail screen's
/// square (or differently-rounded) presentation, instead of snapping.
class SokoniHeroImage extends StatelessWidget {
  const SokoniHeroImage({
    required this.tag,
    required this.child,
    this.borderRadius = 16,
    super.key,
  });

  /// Unique tag shared between the card and detail instances.
  final Object tag;

  final Widget child;

  /// Corner radius to render at when not mid-flight.
  final double borderRadius;

  @override
  Widget build(BuildContext context) {
    return Hero(
      tag: tag,
      flightShuttleBuilder: (
        flightContext,
        animation,
        flightDirection,
        fromContext,
        toContext,
      ) {
        final fromHero = fromContext.widget as Hero;
        final toHero = toContext.widget as Hero;
        final fromImage = fromHero.child as SokoniHeroImage;
        final toImage = toHero.child as SokoniHeroImage;
        final radiusTween = Tween<double>(
          begin: fromImage.borderRadius,
          end: toImage.borderRadius,
        );
        return AnimatedBuilder(
          animation: animation,
          builder: (context, _) {
            return ClipRRect(
              borderRadius: BorderRadius.circular(radiusTween.evaluate(animation)),
              child: flightDirection == HeroFlightDirection.push
                  ? toImage.child
                  : fromImage.child,
            );
          },
        );
      },
      createRectTween: (begin, end) => RectTween(begin: begin, end: end),
      child: ClipRRect(
        borderRadius: BorderRadius.circular(borderRadius),
        child: child,
      ),
    );
  }
}
