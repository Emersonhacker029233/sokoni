import 'dart:math' as math;

import 'package:flutter/material.dart';

import '../theme/colors.dart';

/// Motion primitive 12 — splash to home.
///
/// D-round 2 (client feedback): "not satisfied with the splash — more
/// movement, longer sequence." Rebuilt as a ~3s brand moment: each letter
/// of SOKONI flies in from its own angle around the mark and settles with
/// a spring; the bag mark itself — kept the hero throughout, largest and
/// most central element on screen — scales in with its own spring settle
/// (the "scale with a settle" option from the brief, not "assemble from
/// two shapes": there is only ever one bag-mark asset in this repo —
/// `sokoni_logo_icon.png` — no separate top/bottom-half source exists to
/// assemble from, and fabricating a two-piece asset purely to animate its
/// halves together risked a visible seam for no real gain over a clean
/// spring scale); a one-time shine sweep passes across the finished
/// lockup; a few restrained, low-opacity shapes drift in the background.
/// Then it holds briefly and shrinks toward the nav bar position, same as
/// before.
///
/// The wordmark is still rendered as styled [Text], not an image — see
/// the history below on why that matters here specifically.
///
/// D5 (tester feedback, still true): "too plain" — the first rebuild
/// established the "mark becomes the app" shrink-to-nav-bar idea and the
/// letter-by-letter reveal; this round adds the directional fly-in, the
/// spring, the shine, and the drifting shapes on top of that foundation
/// rather than starting over.
///
/// D5's own original docblock, preserved because the hazard it documents
/// is still exactly what this file must keep avoiding: on the real
/// Android 16 device this was built to protect against, the animation's
/// own `AnimationController` reliably never reached
/// [AnimationStatus.completed] — logged as "animation never completed" —
/// even though the app was otherwise fully alive (first frame painted,
/// Dart running fine). Root cause traced to a placeholder brand asset
/// that had ballooned to 6250x6250 (~149MB decoded as raw RGBA) with no
/// `cacheWidth`/`cacheHeight` hint on the `Image.asset` call inside the
/// animation's `AnimatedBuilder` — see DECISIONS.md for the full trace.
/// Fixing the asset doesn't change the underlying design principle this
/// widget follows now: an animation must never gate routing. Navigation
/// (in [SplashScreen]) runs off a plain [Timer] set to this animation's
/// own intended duration, independent of the `AnimationController`'s
/// actual status. Calls [onComplete] exactly once, either when the
/// animation finishes or immediately if the user taps to skip (honouring
/// [MediaQuery.disableAnimations] by skipping straight to [onComplete]).
///
/// This widget's own timing is entirely cosmetic — [SplashScreen] (the
/// actual navigation gate) runs its own independent timer rather than
/// waiting on [onComplete] or this controller's status, precisely so a
/// slow/stuck animation here can never strand the app on this screen.
/// Do not couple navigation to this controller when touching this file.
/// Every asset draw below still carries a `cacheWidth`/`cacheHeight`
/// hint sized to the real on-screen footprint, for the same reason.
class SokoniSplashTransition extends StatefulWidget {
  const SokoniSplashTransition({required this.onComplete, super.key});

  final VoidCallback onComplete;

  /// [SplashScreen]'s own navigation timer targets this same duration —
  /// kept as one shared constant so the two can never silently drift
  /// apart, without making navigation actually depend on this controller.
  /// "Roughly 3 seconds" per the brief.
  static const duration = Duration(milliseconds: 3000);

  static const _wordmark = 'SOKONI';

  @override
  State<SokoniSplashTransition> createState() => _SokoniSplashTransitionState();
}

class _SokoniSplashTransitionState extends State<SokoniSplashTransition>
    with SingleTickerProviderStateMixin {
  late final AnimationController _controller;
  bool _completed = false;
  bool _started = false;

  @override
  void initState() {
    super.initState();
    _controller = AnimationController(vsync: this, duration: SokoniSplashTransition.duration);
    _controller.addStatusListener((status) {
      if (status == AnimationStatus.completed) _complete();
    });
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (_started) return;
    _started = true;
    if (MediaQuery.disableAnimationsOf(context)) {
      WidgetsBinding.instance.addPostFrameCallback((_) => _complete());
    } else {
      _controller.forward();
    }
  }

  void _complete() {
    if (_completed) return;
    _completed = true;
    widget.onComplete();
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final background = isDark ? SokoniColors.darkSurface : SokoniColors.surface;
    final screenSize = MediaQuery.sizeOf(context);
    final devicePixelRatio = MediaQuery.devicePixelRatioOf(context);

    // ~30% of screen width per the brief, capped both ends so a tiny
    // (watch-sized test surface) or huge (tablet) width never produces a
    // silly mark size.
    final markSize = (screenSize.width * 0.3).clamp(72.0, 220.0);
    // Sized to the mark's actual on-screen footprint at this device's real
    // pixel ratio, not a fixed guess — this is exactly the guard that was
    // missing when a 6250x6250 placeholder asset decoded fresh on every
    // single AnimatedBuilder frame and reliably hung startup on a real
    // device (see DECISIONS.md and this class's own docblock). A generous
    // 2x safety margin on top of the real target size costs nothing (this
    // is still a tiny raster) and tolerates the mark growing a little in a
    // future redesign without silently reintroducing the same bug.
    final cacheSize = (markSize * devicePixelRatio * 2).clamp(72.0, 640.0).round();
    // Radius the letters fly in from — scales with the mark, capped so it
    // never reaches past a small phone's own edges.
    final flyRadius = (markSize * 1.7).clamp(90.0, screenSize.shortestSide * 0.4);

    return GestureDetector(
      onTap: _complete,
      child: Scaffold(
        backgroundColor: background,
        body: Stack(
          children: [
            Positioned.fill(
              child: _DriftingShapes(controller: _controller, screenSize: screenSize),
            ),
            AnimatedBuilder(
              animation: _controller,
              builder: (context, _) {
                // Phase curves, all driven off the one controller — see the
                // class docblock for the overall sequence this composes.
                final markScaleCurve = CurvedAnimation(
                  parent: _controller,
                  curve: const Interval(0, 0.38, curve: Curves.elasticOut),
                );
                final glowInCurve = CurvedAnimation(parent: _controller, curve: const Interval(0, 0.25, curve: Curves.easeOut));
                final glowOutCurve = CurvedAnimation(parent: _controller, curve: const Interval(0.87, 1, curve: Curves.easeIn));
                final shineCurve = CurvedAnimation(
                  parent: _controller,
                  curve: const Interval(0.62, 0.84, curve: Curves.easeInOutCubic),
                );
                final shrinkCurve = CurvedAnimation(parent: _controller, curve: const Interval(0.87, 1, curve: Curves.easeInCubic));

                final markScale = markScaleCurve.value;
                final glowOpacity = (glowInCurve.value * (1 - glowOutCurve.value)).clamp(0.0, 1.0);
                final groupScale = (1 - shrinkCurve.value * 0.75).clamp(0.0, 1.3);
                final dy = shrinkCurve.value * (screenSize.height / 2 - 60);
                final wordmarkFadeOut = 1 - shrinkCurve.value;
                // -1.6 .. 1.6 so the band starts fully hidden off one edge
                // and ends fully hidden off the other — a single pass, not
                // a loop.
                final shineSlide = -1.6 + shineCurve.value * 3.2;

                return Center(
                  child: Transform.translate(
                    offset: Offset(0, dy),
                    child: Transform.scale(
                      scale: groupScale,
                      child: ShaderMask(
                        blendMode: BlendMode.srcATop,
                        shaderCallback: (bounds) {
                          return LinearGradient(
                            begin: Alignment.topLeft,
                            end: Alignment.bottomRight,
                            colors: const [Colors.transparent, Colors.white70, Colors.transparent],
                            stops: const [0.35, 0.5, 0.65],
                            transform: _SlideGradientTransform(shineSlide),
                          ).createShader(bounds);
                        },
                        child: Column(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            Stack(
                              alignment: Alignment.center,
                              children: [
                                // Subtle accent — a soft yellow bloom behind
                                // the mark, not a full background wash (the
                                // brand rule: "yellow is a highlight, never
                                // a wash").
                                Opacity(
                                  opacity: glowOpacity * 0.35,
                                  child: Container(
                                    width: markSize * 1.8,
                                    height: markSize * 1.8,
                                    decoration: const BoxDecoration(
                                      shape: BoxShape.circle,
                                      gradient: RadialGradient(
                                        colors: [SokoniColors.sokoniYellow, Colors.transparent],
                                      ),
                                    ),
                                  ),
                                ),
                                Transform.scale(
                                  scale: markScale.clamp(0.0, 1.3),
                                  child: ClipRRect(
                                    borderRadius: BorderRadius.circular(markSize * 0.29),
                                    child: Image.asset(
                                      'assets/brand/sokoni_logo_icon.png',
                                      width: markSize,
                                      height: markSize,
                                      fit: BoxFit.cover,
                                      cacheWidth: cacheSize,
                                      cacheHeight: cacheSize,
                                    ),
                                  ),
                                ),
                              ],
                            ),
                            SizedBox(height: markSize * 0.12),
                            Opacity(
                              opacity: wordmarkFadeOut,
                              child: _Wordmark(controller: _controller, markSize: markSize, flyRadius: flyRadius),
                            ),
                          ],
                        ),
                      ),
                    ),
                  ),
                );
              },
            ),
          ],
        ),
      ),
    );
  }
}

/// Slides a gradient's stops across its bounds — the standard technique
/// behind one-off "shine sweep" and shimmer effects: the gradient itself
/// never moves, its coordinate space does.
class _SlideGradientTransform extends GradientTransform {
  const _SlideGradientTransform(this.slidePercent);

  final double slidePercent;

  @override
  Matrix4? transform(Rect bounds, {TextDirection? textDirection}) {
    return Matrix4.translationValues(bounds.width * slidePercent, 0.0, 0.0);
  }
}

/// SOKONI, letter by letter — each one flies in from its own angle around
/// the mark (a distinct direction per letter, per the brief) and settles
/// into its row position with a spring, staggered across a shared window
/// on the one controller's timeline.
class _Wordmark extends StatelessWidget {
  const _Wordmark({required this.controller, required this.markSize, required this.flyRadius});

  final AnimationController controller;
  final double markSize;
  final double flyRadius;

  static const _revealStart = 0.18;
  static const _revealEnd = 0.60;
  static const _perLetterWindow = 0.22;

  // One direction per letter, spread around the mark rather than all
  // arriving from the same side — degrees, converted to an offset below.
  static const _angles = [-150.0, -95.0, -35.0, 35.0, 95.0, 150.0];

  @override
  Widget build(BuildContext context) {
    final letters = SokoniSplashTransition._wordmark.split('');
    final span = _revealEnd - _revealStart - _perLetterWindow;
    final step = letters.length > 1 ? span / (letters.length - 1) : 0.0;

    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        for (var i = 0; i < letters.length; i++)
          _AnimatedLetter(
            controller: controller,
            letter: letters[i],
            fontSize: markSize * 0.22,
            start: (_revealStart + step * i).clamp(0.0, 1.0),
            end: (_revealStart + step * i + _perLetterWindow).clamp(0.0, 1.0),
            origin: Offset.fromDirection(_angles[i % _angles.length] * math.pi / 180, flyRadius),
            rotationTurns: (i.isEven ? -1 : 1) * 0.18,
          ),
      ],
    );
  }
}

class _AnimatedLetter extends StatelessWidget {
  const _AnimatedLetter({
    required this.controller,
    required this.letter,
    required this.fontSize,
    required this.start,
    required this.end,
    required this.origin,
    required this.rotationTurns,
  });

  final AnimationController controller;
  final String letter;
  final double fontSize;
  final double start;
  final double end;

  /// Where this letter flies in from, relative to its settled position.
  final Offset origin;

  /// Extra full-turn rotation this letter spins out of on the way in.
  final double rotationTurns;

  @override
  Widget build(BuildContext context) {
    final curve = CurvedAnimation(
      parent: controller,
      // Curves.elasticOut is the "settling into place with a spring" the
      // brief asks for: it overshoots past its target and springs back,
      // rather than easing straight in.
      curve: Interval(start, end > start ? end : start + 0.01, curve: Curves.elasticOut),
    );

    return AnimatedBuilder(
      animation: curve,
      builder: (context, _) {
        // The spring curve intentionally overshoots above 1.0 and can dip
        // fractionally below 0 — clamp only where a value must stay in a
        // valid range (opacity); let translate/rotate keep the raw value
        // so the overshoot-and-settle bounce is actually visible.
        final progress = curve.value;
        final remaining = 1 - progress;
        return Opacity(
          opacity: progress.clamp(0.0, 1.0),
          child: Transform.translate(
            offset: origin * remaining,
            child: Transform.rotate(
              angle: rotationTurns * remaining * 2 * math.pi,
              child: Text(
                letter,
                style: TextStyle(
                  fontSize: fontSize,
                  fontWeight: FontWeight.w800,
                  letterSpacing: 2,
                  color: SokoniColors.sokoniYellow,
                ),
              ),
            ),
          ),
        );
      },
    );
  }
}

/// A few restrained, low-opacity shapes drifting slowly in the
/// background — "product-suggesting" in the loosest sense (a tag, a
/// token, a card), never competing with the mark or wordmark for
/// attention. Fades in as the sequence starts and out again as it shrinks
/// toward the nav bar.
class _DriftingShapes extends StatelessWidget {
  const _DriftingShapes({required this.controller, required this.screenSize});

  final AnimationController controller;
  final Size screenSize;

  static const _specs = [
    (alignment: Alignment(-0.7, -0.55), size: 46.0, shape: BoxShape.circle, freq: 0.6, phase: 0.0),
    (alignment: Alignment(0.75, -0.35), size: 34.0, shape: BoxShape.rectangle, freq: 0.5, phase: 1.4),
    (alignment: Alignment(-0.6, 0.6), size: 30.0, shape: BoxShape.rectangle, freq: 0.7, phase: 2.6),
    (alignment: Alignment(0.65, 0.65), size: 40.0, shape: BoxShape.circle, freq: 0.55, phase: 3.8),
  ];

  @override
  Widget build(BuildContext context) {
    return AnimatedBuilder(
      animation: controller,
      builder: (context, _) {
        final t = controller.value;
        final fadeIn = Curves.easeOut.transform((t / 0.22).clamp(0.0, 1.0));
        final fadeOut = 1 - Curves.easeIn.transform(((t - 0.85) / 0.15).clamp(0.0, 1.0));
        final opacity = (fadeIn * fadeOut).clamp(0.0, 1.0);

        return Stack(
          children: [
            for (final spec in _specs)
              Align(
                alignment: spec.alignment,
                child: Transform.translate(
                  offset: Offset(
                    math.sin(t * 2 * math.pi * spec.freq + spec.phase) * 14,
                    math.cos(t * 2 * math.pi * spec.freq + spec.phase) * 9,
                  ),
                  child: Opacity(
                    opacity: opacity * 0.07,
                    child: Container(
                      width: spec.size,
                      height: spec.size,
                      decoration: BoxDecoration(
                        color: SokoniColors.sokoniYellow,
                        shape: spec.shape,
                        borderRadius: spec.shape == BoxShape.rectangle ? BorderRadius.circular(spec.size * 0.3) : null,
                      ),
                    ),
                  ),
                ),
              ),
          ],
        );
      },
    );
  }
}
