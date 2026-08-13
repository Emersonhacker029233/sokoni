import 'package:flutter/material.dart';

/// Sokoni brand palette, sampled from `assets/brand/sokoni_logo.png`.
///
/// Yellow is a highlight, not a background wash: primary CTA, active chip,
/// rating stars, verified badge, notification dot. Everything else is
/// black-on-white (or white-on-near-black in dark mode) with generous
/// whitespace.
abstract final class SokoniColors {
  static const Color sokoniYellow = Color(0xFFFAC902);
  static const Color sokoniBlack = Color(0xFF0A0A0A);
  static const Color surface = Color(0xFFFFFFFF);
  static const Color surfaceAlt = Color(0xFFF7F7F5);
  static const Color outline = Color(0xFFE6E6E1);
  static const Color success = Color(0xFF12A150);
  static const Color danger = Color(0xFFE5484D);

  // Dark mode surfaces. Yellow stays identical across themes.
  static const Color darkSurface = Color(0xFF0E0E0E);
  static const Color darkSurfaceAlt = Color(0xFF1A1A1A);
  static const Color darkOutline = Color(0xFF2C2C2C);
  static const Color darkOnSurface = Color(0xFFF5F5F3);

  /// Text/icon color guaranteed to meet contrast against [sokoniYellow].
  /// Yellow is light, so black text sits on it in both themes.
  static const Color onYellow = sokoniBlack;
}
