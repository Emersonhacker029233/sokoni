import 'dart:async';

import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../diagnostics/boot_log.dart';
import '../motion/splash_transition.dart';
import 'routes.dart';

/// Wraps [SokoniSplashTransition] and guarantees navigation to the home
/// feed happens regardless of whether the animation itself ever signals
/// completion.
///
/// On the real Android 16 device this was built to protect against, the
/// animation's own `AnimationController` reliably never reached
/// [AnimationStatus.completed] — logged as "animation never completed" —
/// even though the app was otherwise fully alive (first frame painted,
/// Dart running fine). Root cause traced to a placeholder brand asset
/// that had ballooned to 6250x6250 (~149MB decoded as raw RGBA) with no
/// `cacheWidth`/`cacheHeight` hint on the `Image.asset` call inside the
/// animation's `AnimatedBuilder` (see splash_transition.dart, now fixed)
/// — see DECISIONS.md for the full trace. Fixing the asset doesn't change
/// the underlying design principle this widget follows now: an animation
/// must never gate routing. Navigation here runs off a plain [Timer] set
/// to the animation's own intended duration, independent of the
/// `AnimationController`'s actual status — the animation firing its own
/// `onComplete` first is just the same navigation firing slightly early
/// in the common case, not a different code path.
///
/// [_watchdogDuration] is a second, longer-still backstop in case even
/// that timer never fires — it forces the same navigation rather than
/// showing an error screen, since a user should never be stranded on a
/// splash screen with nowhere to go.
class SplashScreen extends StatefulWidget {
  const SplashScreen({super.key});

  @override
  State<SplashScreen> createState() => _SplashScreenState();
}

class _SplashScreenState extends State<SplashScreen> {
  // D5 (tester feedback): reads SokoniSplashTransition's own duration
  // constant so the two can never silently drift apart — this is just a
  // plain compile-time value, not a dependency on the animation's actual
  // controller/status, which is the coupling this class's own docblock
  // warns against; navigation still runs off this independent Timer.
  static const _navigateDuration = SokoniSplashTransition.duration;
  static const _watchdogDuration = Duration(seconds: 5);

  Timer? _navigateTimer;
  Timer? _watchdog;
  bool _navigated = false;

  @override
  void initState() {
    super.initState();
    BootLog.step('SplashScreen.initState() start');

    _navigateTimer = Timer(_navigateDuration, () {
      BootLog.step('SplashScreen navigate timer fired');
      _navigate();
    });
    _watchdog = Timer(_watchdogDuration, () {
      BootLog.step('SplashScreen watchdog fired — forcing navigation');
      _navigate();
    });
    BootLog.step('SplashScreen timers armed');
  }

  void _onAnimationComplete() {
    BootLog.step('SplashScreen animation complete');
    _navigate();
  }

  void _navigate() {
    if (_navigated || !mounted) return;
    _navigated = true;
    _navigateTimer?.cancel();
    _watchdog?.cancel();
    BootLog.step('SplashScreen navigating to home');
    context.go(SokoniRoutes.home);
  }

  @override
  void dispose() {
    _navigateTimer?.cancel();
    _watchdog?.cancel();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return SokoniSplashTransition(onComplete: _onAnimationComplete);
  }
}
