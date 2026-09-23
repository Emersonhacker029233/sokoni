import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../providers.dart';

const _themeModeKey = 'app_theme_mode';

/// Part 4 (client feedback): Settings' Appearance section — "Light,
/// Dark, and System theme — the app already has a dark theme with no
/// way to choose it." Same device-wide, unscoped key/value persistence
/// as [LocaleController] (core/l10n/locale_controller.dart) — a chosen
/// theme is a device preference, not a per-account one, so it
/// deliberately survives `AuthStateController.signOut()`'s
/// `appDatabaseProvider.clearAll()` (which only ever touches the
/// cached-listings tables, never this generic key/value one) and an
/// account switch, exactly like the language choice.
class ThemeModeController extends AsyncNotifier<ThemeMode> {
  @override
  Future<ThemeMode> build() async {
    final saved = await ref.watch(appDatabaseProvider).getKeyValue(_themeModeKey);
    return switch (saved) {
      'light' => ThemeMode.light,
      'dark' => ThemeMode.dark,
      _ => ThemeMode.system,
    };
  }

  Future<void> setThemeMode(ThemeMode mode) async {
    await ref.read(appDatabaseProvider).setKeyValue(_themeModeKey, mode.name);
    state = AsyncData(mode);
  }
}

final themeModeControllerProvider = AsyncNotifierProvider<ThemeModeController, ThemeMode>(ThemeModeController.new);
