import 'package:flutter/material.dart';

import 'colors.dart';

/// Sokoni type scale, built on Plus Jakarta Sans.
///
/// display: 32/700, title: 20/600, body: 15/400, caption: 13/500 — tight
/// letter spacing (-0.5) on headings only.
///
/// The font is wired up as a real, locally bundled asset — not
/// `google_fonts`' runtime network fetch (removed entirely; see
/// DECISIONS.md). That fetch had no timeout of its own, and downloading a
/// typeface at startup over the network is wrong for this market's
/// "patchy 3G/4G" target (CLAUDE.md) regardless of whether it was the
/// cause of the startup hang that surfaced it.
///
/// The real Plus Jakarta Sans `.ttf` files aren't available in this
/// environment (no network access, none cached locally) to actually drop
/// in, so `_fontFamily` below is wired up and ready but currently has no
/// matching asset — Flutter falls back to the platform default font
/// (Roboto on Android) until the real files land. To finish this: get the
/// four weights used here (400, 500, 600, 700) as `.ttf` files from
/// https://fonts.google.com/specimen/Plus+Jakarta+Sans, put them in
/// `assets/fonts/`, and uncomment the `fonts:` block in pubspec.yaml.
abstract final class SokoniTypography {
  static const _fontFamily = 'PlusJakartaSans';

  /// Only eight of `TextTheme`'s roles are overridden below with explicit,
  /// theme-aware colours — the rest (`headlineLarge/Medium/Small`,
  /// `titleSmall`, `labelMedium`, `displayMedium/Small`) inherit whatever
  /// [base] provides. `Typography.material2021().black` and `.white` are
  /// fixed, brightness-specific variants (dark text for light surfaces,
  /// light text for dark surfaces respectively) — using `.black`
  /// unconditionally for *both* themes, as this used to do, meant every
  /// non-overridden role stayed dark-on-dark in dark mode. `AlertDialog`'s
  /// Material 3 default title style reads `headlineSmall` specifically
  /// (see `dialog.dart`'s `_DialogDefaultsM3`), which is exactly how the
  /// location-permission dialog ended up unreadable — see DECISIONS.md.
  static TextTheme textTheme(Color onSurface, Brightness brightness) {
    final base = (brightness == Brightness.dark
            ? Typography.material2021().white
            : Typography.material2021().black)
        .apply(fontFamily: _fontFamily);
    return base.copyWith(
      displayLarge: const TextStyle(fontFamily: _fontFamily).copyWith(
        fontSize: 32,
        fontWeight: FontWeight.w700,
        letterSpacing: -0.5,
        height: 1.2,
        color: onSurface,
      ),
      titleLarge: const TextStyle(fontFamily: _fontFamily).copyWith(
        fontSize: 20,
        fontWeight: FontWeight.w600,
        letterSpacing: -0.5,
        height: 1.3,
        color: onSurface,
      ),
      titleMedium: const TextStyle(fontFamily: _fontFamily).copyWith(
        fontSize: 17,
        fontWeight: FontWeight.w600,
        letterSpacing: -0.3,
        height: 1.3,
        color: onSurface,
      ),
      bodyLarge: const TextStyle(fontFamily: _fontFamily).copyWith(
        fontSize: 15,
        fontWeight: FontWeight.w400,
        height: 1.45,
        color: onSurface,
      ),
      bodyMedium: const TextStyle(fontFamily: _fontFamily).copyWith(
        fontSize: 15,
        fontWeight: FontWeight.w400,
        height: 1.45,
        color: onSurface,
      ),
      labelLarge: const TextStyle(fontFamily: _fontFamily).copyWith(
        fontSize: 15,
        fontWeight: FontWeight.w600,
        height: 1.2,
        color: onSurface,
      ),
      bodySmall: const TextStyle(fontFamily: _fontFamily).copyWith(
        fontSize: 13,
        fontWeight: FontWeight.w500,
        height: 1.3,
        color: onSurface.withValues(alpha: 0.7),
      ),
      labelSmall: const TextStyle(fontFamily: _fontFamily).copyWith(
        fontSize: 13,
        fontWeight: FontWeight.w500,
        height: 1.3,
        color: onSurface.withValues(alpha: 0.7),
      ),
    );
  }

  static TextTheme get light => textTheme(SokoniColors.sokoniBlack, Brightness.light);
  static TextTheme get dark => textTheme(SokoniColors.darkOnSurface, Brightness.dark);
}
