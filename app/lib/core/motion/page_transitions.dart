import 'package:flutter/material.dart';

/// Motion primitive 1 — page transitions.
///
/// Three reusable transition builders shared between plain [Navigator]
/// routes (used here, in the motion gallery) and go_router's
/// `CustomTransitionPage` (wired up in `core/router/transitions.dart` once
/// the router lands in Phase 3). All three run at [kSokoniPageDuration] with
/// [Curves.easeOutCubic] and honour [MediaQuery.disableAnimations] by
/// collapsing to an instant cross-fade.
const Duration kSokoniPageDuration = Duration(milliseconds: 280);

/// Shared-axis X — for navigating between sibling screens at the same
/// depth (e.g. switching tabs' root content).
class SharedAxisXPageRoute<T> extends PageRouteBuilder<T> {
  SharedAxisXPageRoute({required this.child, super.settings})
    : super(
        transitionDuration: kSokoniPageDuration,
        reverseTransitionDuration: kSokoniPageDuration,
        pageBuilder: (context, animation, secondaryAnimation) => child,
        transitionsBuilder: (context, animation, secondaryAnimation, child) {
          if (MediaQuery.disableAnimationsOf(context)) return child;
          final curved = CurvedAnimation(
            parent: animation,
            curve: Curves.easeOutCubic,
            reverseCurve: Curves.easeInCubic,
          );
          final incoming = Tween<Offset>(
            begin: const Offset(0.3, 0),
            end: Offset.zero,
          ).animate(curved);
          final outgoingCurved = CurvedAnimation(
            parent: secondaryAnimation,
            curve: Curves.easeOutCubic,
          );
          final outgoing = Tween<Offset>(
            begin: Offset.zero,
            end: const Offset(-0.3, 0),
          ).animate(outgoingCurved);
          return SlideTransition(
            position: outgoing,
            child: SlideTransition(
              position: incoming,
              child: FadeTransition(opacity: curved, child: child),
            ),
          );
        },
      );

  final Widget child;
}

/// Shared-axis Z — for drilling into detail (list → detail), a subtle
/// scale + fade that reads as moving forward in depth.
class SharedAxisZPageRoute<T> extends PageRouteBuilder<T> {
  SharedAxisZPageRoute({required this.child, super.settings})
    : super(
        transitionDuration: kSokoniPageDuration,
        reverseTransitionDuration: kSokoniPageDuration,
        pageBuilder: (context, animation, secondaryAnimation) => child,
        transitionsBuilder: (context, animation, secondaryAnimation, child) {
          if (MediaQuery.disableAnimationsOf(context)) return child;
          final curved = CurvedAnimation(
            parent: animation,
            curve: Curves.easeOutCubic,
            reverseCurve: Curves.easeInCubic,
          );
          final scale = Tween<double>(
            begin: 0.92,
            end: 1,
          ).animate(curved);
          return FadeTransition(
            opacity: curved,
            child: ScaleTransition(scale: scale, child: child),
          );
        },
      );

  final Widget child;
}

/// Fade-through — for switching between unrelated destinations at the same
/// level (bottom-nav tabs): outgoing content fades out, incoming fades in
/// with a slight scale-up, never overlapping in a way that reads as sliding.
class FadeThroughPageRoute<T> extends PageRouteBuilder<T> {
  FadeThroughPageRoute({required this.child, super.settings})
    : super(
        transitionDuration: kSokoniPageDuration,
        reverseTransitionDuration: kSokoniPageDuration,
        opaque: true,
        pageBuilder: (context, animation, secondaryAnimation) => child,
        transitionsBuilder: (context, animation, secondaryAnimation, child) {
          if (MediaQuery.disableAnimationsOf(context)) return child;
          final fadeIn = CurvedAnimation(
            parent: animation,
            curve: const Interval(0.3, 1, curve: Curves.easeOut),
          );
          final scale = Tween<double>(
            begin: 0.98,
            end: 1,
          ).animate(fadeIn);
          return FadeTransition(
            opacity: fadeIn,
            child: ScaleTransition(scale: scale, child: child),
          );
        },
      );

  final Widget child;
}
