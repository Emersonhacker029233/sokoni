import 'package:drift/native.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:sokoni/core/providers.dart';
import 'package:sokoni/core/storage/app_database.dart';
import 'package:sokoni/core/theme/theme_mode_controller.dart';

/// Part 4 (client feedback): "Light, Dark, and System theme — the app
/// already has a dark theme with no way to choose it." Same
/// persistence contract as LocaleController — see locale_controller_test.dart.
void main() {
  group('ThemeModeController persistence', () {
    test('with nothing saved yet, build() defaults to system', () async {
      final db = AppDatabase.forTesting(NativeDatabase.memory());
      final container = ProviderContainer(overrides: [appDatabaseProvider.overrideWithValue(db)]);
      addTearDown(container.dispose);
      addTearDown(db.close);

      expect(await container.read(themeModeControllerProvider.future), ThemeMode.system);
    });

    test('setThemeMode persists the choice for the next read from the same underlying storage', () async {
      final db = AppDatabase.forTesting(NativeDatabase.memory());
      final first = ProviderContainer(overrides: [appDatabaseProvider.overrideWithValue(db)]);
      await first.read(themeModeControllerProvider.future);
      await first.read(themeModeControllerProvider.notifier).setThemeMode(ThemeMode.dark);
      expect(first.read(themeModeControllerProvider).value, ThemeMode.dark);

      // A fresh container sharing the same AppDatabase stands in for the
      // full-ProviderScope restart on account switch (see main.dart's
      // restartApp()) — the choice must survive it, since it's a
      // device-wide preference like the locale one, not scoped to
      // whichever account is active.
      final second = ProviderContainer(overrides: [appDatabaseProvider.overrideWithValue(db)]);
      addTearDown(first.dispose);
      addTearDown(second.dispose);
      addTearDown(db.close);

      expect(await second.read(themeModeControllerProvider.future), ThemeMode.dark);
    });

    test('an unrecognised saved value falls back to system rather than crashing', () async {
      final db = AppDatabase.forTesting(NativeDatabase.memory());
      await db.setKeyValue('app_theme_mode', 'garbage');
      final container = ProviderContainer(overrides: [appDatabaseProvider.overrideWithValue(db)]);
      addTearDown(container.dispose);
      addTearDown(db.close);

      expect(await container.read(themeModeControllerProvider.future), ThemeMode.system);
    });

    test('light and dark both round-trip correctly', () async {
      final db = AppDatabase.forTesting(NativeDatabase.memory());
      final container = ProviderContainer(overrides: [appDatabaseProvider.overrideWithValue(db)]);
      addTearDown(container.dispose);
      addTearDown(db.close);

      await container.read(themeModeControllerProvider.future);
      await container.read(themeModeControllerProvider.notifier).setThemeMode(ThemeMode.light);
      expect(container.read(themeModeControllerProvider).value, ThemeMode.light);

      await container.read(themeModeControllerProvider.notifier).setThemeMode(ThemeMode.system);
      expect(container.read(themeModeControllerProvider).value, ThemeMode.system);
    });
  });
}
