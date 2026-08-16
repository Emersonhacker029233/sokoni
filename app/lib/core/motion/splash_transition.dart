import 'package:flutter/material.dart';

import '../theme/colors.dart';

/// Motion primitive 12 — splash to home.
///
/// The Sokoni bag mark scales in and settles (elastic overshoot), holds
/// briefly, then shrinks and slides toward where the nav bar's centre
/// action sits — reading as "the mark becomes the app". Runs for at most
/// 900ms end-to-end and calls [onComplete] exactly once, either when the
/// animation finishes or immediately if the user taps to skip (honouring
/// [MediaQuery.disableAnimations] by skipping straight to [onComplete]).
class SokoniSplashTransition extends StatefulWidget {
  const SokoniSplashTransition({required this.onComplete, super.key});

  final VoidCallback onComplete;

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
    _controller = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 900),
    );
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

    return GestureDetector(
      onTap: _complete,
      child: Scaffold(
        backgroundColor: background,
        body: AnimatedBuilder(
          animation: _controller,
          builder: (context, _) {
            // Scale-and-settle for the first 55%, then shrink+shift toward
            // the nav bar's centre position (bottom-centre) for the rest.
            final settleCurve = CurvedAnimation(
              parent: _controller,
              curve: const Interval(0, 0.55, curve: Curves.elasticOut),
            );
            final shrinkCurve = CurvedAnimation(
              parent: _controller,
              curve: const Interval(0.55, 1, curve: Curves.easeInCubic),
            );

            final scale = settleCurve.value * (1 - shrinkCurve.value * 0.7);
            final screenSize = MediaQuery.sizeOf(context);
            final dy = shrinkCurve.value * (screenSize.height / 2 - 60);

            return Center(
              child: Transform.translate(
                offset: Offset(0, dy),
                child: Transform.scale(
                  scale: scale.clamp(0.0, 1.3),
                  child: ClipRRect(
                    borderRadius: BorderRadius.circular(28),
                    child: Image.asset(
                      'assets/brand/sokoni_logo_icon.png',
                      width: 96,
                      height: 96,
                      fit: BoxFit.cover,
                      // Caps decode resolution regardless of the source
                      // asset's actual size — see DECISIONS.md: a
                      // placeholder asset was found at 6250x6250
                      // (~149MB decoded as raw RGBA) being decoded fresh
                      // on every AnimatedBuilder rebuild for a 96x96
                      // target with no cache hint at all. 192 covers up to
                      // 2x device pixel ratio at this display size.
                      cacheWidth: 192,
                      cacheHeight: 192,
                    ),
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
