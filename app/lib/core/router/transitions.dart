import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../motion/page_transitions.dart';

/// go_router `CustomTransitionPage` wrappers around the shared motion
/// primitives in `core/motion/page_transitions.dart`, so the same
/// shared-axis/fade-through curves used in the motion gallery drive real
/// navigation. Shared-axis X for sibling screens, shared-axis Z for
/// drilling into detail, fade-through for switching bottom-nav tabs.
CustomTransitionPage<T> sharedAxisXPage<T>({
  required LocalKey key,
  required Widget child,
}) {
  return CustomTransitionPage<T>(
    key: key,
    child: child,
    transitionDuration: kSokoniPageDuration,
    reverseTransitionDuration: kSokoniPageDuration,
    transitionsBuilder: (context, animation, secondaryAnimation, child) =>
        _sharedAxisX(context, animation, secondaryAnimation, child),
  );
}

CustomTransitionPage<T> sharedAxisZPage<T>({
  required LocalKey key,
  required Widget child,
}) {
  return CustomTransitionPage<T>(
    key: key,
    child: child,
    transitionDuration: kSokoniPageDuration,
    reverseTransitionDuration: kSokoniPageDuration,
    transitionsBuilder: (context, animation, secondaryAnimation, child) =>
        _sharedAxisZ(context, animation, child),
  );
}

CustomTransitionPage<T> fadeThroughPage<T>({
  required LocalKey key,
  required Widget child,
}) {
  return CustomTransitionPage<T>(
    key: key,
    child: child,
    transitionDuration: kSokoniPageDuration,
    reverseTransitionDuration: kSokoniPageDuration,
    transitionsBuilder: (context, animation, secondaryAnimation, child) =>
        _fadeThrough(context, animation, child),
  );
}

Widget _sharedAxisX(
  BuildContext context,
  Animation<double> animation,
  Animation<double> secondaryAnimation,
  Widget child,
) {
  if (MediaQuery.disableAnimationsOf(context)) return child;
  final curved = CurvedAnimation(parent: animation, curve: Curves.easeOutCubic);
  final incoming = Tween<Offset>(begin: const Offset(0.3, 0), end: Offset.zero).animate(curved);
  final outgoing = Tween<Offset>(begin: Offset.zero, end: const Offset(-0.3, 0)).animate(
    CurvedAnimation(parent: secondaryAnimation, curve: Curves.easeOutCubic),
  );
  return SlideTransition(
    position: outgoing,
    child: SlideTransition(
      position: incoming,
      child: FadeTransition(opacity: curved, child: child),
    ),
  );
}

Widget _sharedAxisZ(BuildContext context, Animation<double> animation, Widget child) {
  if (MediaQuery.disableAnimationsOf(context)) return child;
  final curved = CurvedAnimation(parent: animation, curve: Curves.easeOutCubic);
  final scale = Tween<double>(begin: 0.92, end: 1).animate(curved);
  return FadeTransition(opacity: curved, child: ScaleTransition(scale: scale, child: child));
}

Widget _fadeThrough(BuildContext context, Animation<double> animation, Widget child) {
  if (MediaQuery.disableAnimationsOf(context)) return child;
  final fadeIn = CurvedAnimation(
    parent: animation,
    curve: const Interval(0.3, 1, curve: Curves.easeOut),
  );
  final scale = Tween<double>(begin: 0.98, end: 1).animate(fadeIn);
  return FadeTransition(opacity: fadeIn, child: ScaleTransition(scale: scale, child: child));
}
