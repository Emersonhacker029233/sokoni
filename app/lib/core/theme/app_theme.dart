import 'package:flutter/material.dart';

import 'colors.dart';
import 'dimens.dart';
import 'typography.dart';

/// Builds the light and dark [ThemeData] for Sokoni.
///
/// Shape language: 16dp cards, 12dp chips/fields, 28dp primary button.
/// No drop shadows anywhere except a soft ambient shadow on floating
/// elements (bottom sheets, FAB) — everything else uses a 1px outline
/// border instead.
abstract final class SokoniTheme {
  static ThemeData get light => _build(brightness: Brightness.light);
  static ThemeData get dark => _build(brightness: Brightness.dark);

  static ThemeData _build({required Brightness brightness}) {
    final isDark = brightness == Brightness.dark;

    final surface = isDark ? SokoniColors.darkSurface : SokoniColors.surface;
    final surfaceAlt =
        isDark ? SokoniColors.darkSurfaceAlt : SokoniColors.surfaceAlt;
    final outline = isDark ? SokoniColors.darkOutline : SokoniColors.outline;
    final onSurface =
        isDark ? SokoniColors.darkOnSurface : SokoniColors.sokoniBlack;

    final colorScheme = ColorScheme(
      brightness: brightness,
      primary: SokoniColors.sokoniYellow,
      onPrimary: SokoniColors.onYellow,
      secondary: SokoniColors.sokoniBlack,
      onSecondary: SokoniColors.surface,
      error: SokoniColors.danger,
      onError: SokoniColors.surface,
      surface: surface,
      onSurface: onSurface,
      surfaceContainerHighest: surfaceAlt,
      outline: outline,
    );

    final textTheme = isDark ? SokoniTypography.dark : SokoniTypography.light;

    return ThemeData(
      useMaterial3: true,
      brightness: brightness,
      colorScheme: colorScheme,
      scaffoldBackgroundColor: surface,
      textTheme: textTheme,
      fontFamily: textTheme.bodyMedium?.fontFamily,
      splashFactory: InkSparkle.splashFactory,
      appBarTheme: AppBarTheme(
        backgroundColor: surface,
        foregroundColor: onSurface,
        elevation: 0,
        scrolledUnderElevation: 0,
        centerTitle: false,
        titleTextStyle: textTheme.titleLarge,
        surfaceTintColor: Colors.transparent,
      ),
      cardTheme: CardThemeData(
        color: surfaceAlt,
        elevation: 0,
        margin: EdgeInsets.zero,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(SokoniDimens.radiusCard),
          side: BorderSide(color: outline),
        ),
      ),
      chipTheme: ChipThemeData(
        backgroundColor: surfaceAlt,
        selectedColor: SokoniColors.sokoniYellow,
        disabledColor: surfaceAlt,
        labelStyle: textTheme.labelLarge,
        secondaryLabelStyle: textTheme.labelLarge?.copyWith(
          color: SokoniColors.onYellow,
        ),
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(SokoniDimens.radiusChip),
          side: BorderSide(color: outline),
        ),
        side: BorderSide.none,
        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
      ),
      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        fillColor: surfaceAlt,
        contentPadding: const EdgeInsets.symmetric(
          horizontal: SokoniDimens.space16,
          vertical: SokoniDimens.space16,
        ),
        border: OutlineInputBorder(
          borderRadius: BorderRadius.circular(SokoniDimens.radiusField),
          borderSide: BorderSide(color: outline),
        ),
        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(SokoniDimens.radiusField),
          borderSide: BorderSide(color: outline),
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(SokoniDimens.radiusField),
          borderSide: const BorderSide(
            color: SokoniColors.sokoniYellow,
            width: 1.5,
          ),
        ),
        errorBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(SokoniDimens.radiusField),
          borderSide: const BorderSide(color: SokoniColors.danger),
        ),
      ),
      elevatedButtonTheme: ElevatedButtonThemeData(
        style: ElevatedButton.styleFrom(
          backgroundColor: SokoniColors.sokoniYellow,
          foregroundColor: SokoniColors.onYellow,
          disabledBackgroundColor: outline,
          disabledForegroundColor: onSurface.withValues(alpha: 0.4),
          elevation: 0,
          minimumSize: const Size.fromHeight(SokoniDimens.minTapTarget + 4),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(SokoniDimens.radiusButton),
          ),
          textStyle: textTheme.labelLarge,
        ),
      ),
      outlinedButtonTheme: OutlinedButtonThemeData(
        style: OutlinedButton.styleFrom(
          foregroundColor: onSurface,
          side: BorderSide(color: outline),
          minimumSize: const Size.fromHeight(SokoniDimens.minTapTarget + 4),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(SokoniDimens.radiusButton),
          ),
          textStyle: textTheme.labelLarge,
        ),
      ),
      textButtonTheme: TextButtonThemeData(
        style: TextButton.styleFrom(
          foregroundColor: onSurface,
          minimumSize: const Size(0, SokoniDimens.minTapTarget),
          textStyle: textTheme.labelLarge,
        ),
      ),
      bottomSheetTheme: BottomSheetThemeData(
        backgroundColor: surface,
        modalBackgroundColor: surface,
        shape: const RoundedRectangleBorder(
          borderRadius: BorderRadius.vertical(
            top: Radius.circular(SokoniDimens.radiusSheet),
          ),
        ),
        showDragHandle: false,
      ),
      dividerTheme: DividerThemeData(color: outline, thickness: 1, space: 1),
      snackBarTheme: SnackBarThemeData(
        backgroundColor: onSurface,
        contentTextStyle: textTheme.bodyMedium?.copyWith(color: surface),
        behavior: SnackBarBehavior.floating,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(SokoniDimens.radiusField),
        ),
      ),
      navigationBarTheme: NavigationBarThemeData(
        backgroundColor: surface,
        surfaceTintColor: Colors.transparent,
        indicatorColor: Colors.transparent,
        elevation: 0,
        height: SokoniDimens.navBarHeight,
        labelTextStyle: WidgetStateProperty.resolveWith((states) {
          final selected = states.contains(WidgetState.selected);
          return textTheme.labelSmall?.copyWith(
            color: selected ? onSurface : onSurface.withValues(alpha: 0.5),
            fontWeight: selected ? FontWeight.w600 : FontWeight.w500,
          );
        }),
      ),
      progressIndicatorTheme: const ProgressIndicatorThemeData(
        color: SokoniColors.sokoniYellow,
      ),
      dialogTheme: DialogThemeData(
        backgroundColor: surface,
        // Explicit, rather than left to AlertDialog's own Material 3
        // default colour-role resolution — this app's ColorScheme only
        // sets a handful of roles (primary/secondary/surface/onSurface/
        // error/outline), so any role Flutter's defaults reach for that
        // isn't one of those (onSurfaceVariant, in particular) falls back
        // to a generic Material baseline with no guaranteed relationship
        // to this app's actual dark surface (#0E0E0E) — which is exactly
        // how the location-permission dialog ended up rendering
        // near-invisible dark text on a dark surface. Pulling straight
        // from textTheme (already onSurface-coloured per brightness, see
        // SokoniTypography) removes that indirection entirely.
        titleTextStyle: textTheme.titleLarge,
        contentTextStyle: textTheme.bodyMedium,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(SokoniDimens.radiusCard),
        ),
      ),
    );
  }
}
