import 'package:drift/native.dart';
import 'package:flutter/widgets.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:sokoni/core/l10n/locale_controller.dart';
import 'package:sokoni/core/providers.dart';
import 'package:sokoni/core/storage/app_database.dart';

/// Language audit (client feedback): "Default to Kiswahili when the
/// device locale isn't explicitly English... Persist the choice across
/// restarts and across account switches."
void main() {
  group('resolveDeviceLocale', () {
    test('an English device locale resolves to English', () {
      expect(resolveDeviceLocale([const Locale('en', 'US')]).languageCode, 'en');
    });

    test('a non-English device locale resolves to Kiswahili, not the device language', () {
      expect(resolveDeviceLocale([const Locale('fr', 'FR')]).languageCode, 'sw');
    });

    test('a device with no locales at all resolves to Kiswahili', () {
      expect(resolveDeviceLocale(null).languageCode, 'sw');
      expect(resolveDeviceLocale(const []).languageCode, 'sw');
    });

    test('English anywhere in a multi-locale device list is honoured', () {
      // A device listing Swahili first but English among its other
      // preferred languages still counts as "explicitly English" —
      // the rule is "isn't explicitly English", not "isn't English first".
      expect(resolveDeviceLocale([const Locale('fr'), const Locale('en')]).languageCode, 'en');
    });
  });

  group('LocaleController persistence', () {
    test('with nothing saved yet, build() resolves to null (device-based default applies)', () async {
      final db = AppDatabase.forTesting(NativeDatabase.memory());
      final container = ProviderContainer(overrides: [appDatabaseProvider.overrideWithValue(db)]);
      addTearDown(container.dispose);
      addTearDown(db.close);

      expect(await container.read(localeControllerProvider.future), isNull);
    });

    test('setLocale persists the choice for the next read from the same underlying storage', () async {
      final db = AppDatabase.forTesting(NativeDatabase.memory());
      final first = ProviderContainer(overrides: [appDatabaseProvider.overrideWithValue(db)]);
      await first.read(localeControllerProvider.future);
      await first.read(localeControllerProvider.notifier).setLocale('en');
      expect(first.read(localeControllerProvider).value?.languageCode, 'en');

      // A fresh container sharing the same AppDatabase stands in for the
      // full-ProviderScope restart on account switch (see main.dart's
      // restartApp()) — the choice must survive it, since it's a
      // device-wide preference, not scoped to whichever account is active.
      final second = ProviderContainer(overrides: [appDatabaseProvider.overrideWithValue(db)]);
      addTearDown(first.dispose);
      addTearDown(second.dispose);
      addTearDown(db.close);

      expect(await second.read(localeControllerProvider.future), const Locale('en'));
    });

    test('an unsupported saved value is ignored rather than crashing', () async {
      final db = AppDatabase.forTesting(NativeDatabase.memory());
      await db.setKeyValue('app_locale', 'fr');
      final container = ProviderContainer(overrides: [appDatabaseProvider.overrideWithValue(db)]);
      addTearDown(container.dispose);
      addTearDown(db.close);

      expect(await container.read(localeControllerProvider.future), isNull);
    });
  });
}
