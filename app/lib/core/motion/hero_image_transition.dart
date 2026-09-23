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
        // Bug (client feedback, part of the type-cast family): this used
        // to read `(fromContext.widget as Hero).child as SokoniHeroImage`
        // — but a Hero's own `.child` is the `ClipRRect` this class wraps
        // its child in below, never the `SokoniHeroImage` itself, so that
        // cast threw a real `TypeError` ("type 'ClipRRect' is not a
        // subtype of type 'SokoniHeroImage'") on every single Hero
        // flight through this class, confirmed via a widget test driving
        // an actual push. `fromContext`/`toContext` are the *Hero*
        // element's own context; `SokoniHeroImage` is that Hero's direct
        // parent in the tree, so its own widget — not its already-built
        // child — is found by walking one step up from there instead.
        final fromImage = fromContext.findAncestorWidgetOfExactType<SokoniHeroImage>()!;
        final toImage = toContext.findAncestorWidgetOfExactType<SokoniHeroImage>()!;
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
