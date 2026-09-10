import 'package:flutter/material.dart';

import '../theme/colors.dart';

/// Motion primitive 12 — splash to home.
///
/// D5 (tester feedback): "too plain" — this is the brand-moment rebuild.
/// A ~1.8s sequence: the bag mark scales in with a spring settle at a real
/// size (~30% of screen width, not a fixed 96px), a soft yellow glow
/// blooms in behind it, the SOKONI wordmark reveals letter-by-letter in
/// brand yellow underneath, everything holds briefly, then the whole
/// group shrinks and slides toward the nav bar's centre position — "the
/// mark becomes the app" — exactly as the original design still calls
/// for, just built up to properly first instead of appearing instantly.
///
/// The wordmark is rendered as styled [Text], not an image — CLAUDE.md's
/// own decision to keep the *launcher icon* to the bag mark alone was
/// about the icon shrinking illegibly at mipmap sizes (see DECISIONS.md);
/// a full-screen splash has no such constraint, and letters as text carry
/// zero asset-decode risk at all, which matters a great deal here — see
/// the class docblock below on the prior real startup hang this screen
/// caused. Calls [onComplete] exactly once, either when the animation
/// finishes or immediately if the user taps to skip (honouring
/// [MediaQuery.disableAnimations] by skipping straight to [onComplete]).
///
/// This widget's own timing is entirely cosmetic — [SplashScreen] (the
/// actual navigation gate) runs its own independent timer rather than
/// waiting on [onComplete] or this controller's status, precisely so a
/// slow/stuck animation here can never strand the app on this screen.
/// Do not couple navigation to this controller when touching this file.
class SokoniSplashTransition extends StatefulWidget {
  const SokoniSplashTransition({required this.onComplete, super.key});

  final VoidCallback onComplete;

  /// [SplashScreen]'s own navigation timer targets this same duration —
  /// kept as one shared constant so the two can never silently drift
  /// apart, without making navigation actually depend on this controller.
  static const duration = Duration(milliseconds: 1800);

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
    // device (see DECISIONS.md and SplashScreen's own docblock). A
    // generous 2x safety margin on top of the real target size costs
    // nothing (this is still a tiny raster) and tolerates the mark
    // growing a little in a future redesign without silently
    // reintroducing the same bug.
    final cacheSize = (markSize * devicePixelRatio * 2).clamp(72.0, 640.0).round();

    return GestureDetector(
      onTap: _complete,
      child: Scaffold(
        backgroundColor: background,
        body: AnimatedBuilder(
          animation: _controller,
          builder: (context, _) {
            // Phase curves, all driven off the one controller — see the
            // class docblock for the overall sequence this composes.
            final markScaleCurve = CurvedAnimation(parent: _controller, curve: const Interval(0, 0.4, curve: Curves.elasticOut));
            final glowInCurve = CurvedAnimation(parent: _controller, curve: const Interval(0, 0.3, curve: Curves.easeOut));
            final glowOutCurve = CurvedAnimation(parent: _controller, curve: const Interval(0.78, 1, curve: Curves.easeIn));
            final shrinkCurve = CurvedAnimation(parent: _controller, curve: const Interval(0.78, 1, curve: Curves.easeInCubic));

            final markScale = markScaleCurve.value;
            final glowOpacity = (glowInCurve.value * (1 - glowOutCurve.value)).clamp(0.0, 1.0);
            final groupScale = (1 - shrinkCurve.value * 0.75).clamp(0.0, 1.3);
            final dy = shrinkCurve.value * (screenSize.height / 2 - 60);
            final wordmarkFadeOut = 1 - shrinkCurve.value;

            return Center(
              child: Transform.translate(
                offset: Offset(0, dy),
                child: Transform.scale(
                  scale: groupScale,
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Stack(
                        alignment: Alignment.center,
                        children: [
                          // Subtle accent — a soft yellow bloom behind the
                          // mark, not a full background wash (the brand
                          // rule: "yellow is a highlight, never a wash").
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
                        child: _Wordmark(controller: _controller, markSize: markSize),
                      ),
                    ],
                  ),
                ),
              ),
            );
          },
        ),
      ),
    );
  }
}

/// SOKONI, letter by letter — each letter fades and rises into place on
/// its own short, staggered window within the shared controller's
/// timeline (the same fade+translateY shape CLAUDE.md's "Staggered list
/// reveal" primitive already uses elsewhere, applied here to characters
/// instead of list rows).
class _Wordmark extends StatelessWidget {
  const _Wordmark({required this.controller, required this.markSize});

  final AnimationController controller;
  final double markSize;

  static const _revealStart = 0.38;
  static const _revealEnd = 0.68;
  static const _perLetterWindow = 0.22;

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
  });

  final AnimationController controller;
  final String letter;
  final double fontSize;
  final double start;
  final double end;

  @override
  Widget build(BuildContext context) {
    final curve = CurvedAnimation(
      parent: controller,
      curve: Interval(start, end > start ? end : start + 0.01, curve: Curves.easeOutCubic),
    );

    return AnimatedBuilder(
      animation: curve,
      builder: (context, _) {
        return Opacity(
          opacity: curve.value,
          child: Transform.translate(
            offset: Offset(0, (1 - curve.value) * 12),
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
        );
      },
    );
  }
}
