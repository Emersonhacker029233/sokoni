import 'package:flutter/widgets.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../providers.dart';

const _localeKey = 'app_locale';

/// Every language this app can display, in picker order. Adding a
/// language is meant to be "add a locale file and a flag, with no code
/// change" (Part 2, client feedback) — this list is the one place that
/// still needs a one-line addition; everything that reads it (the
/// picker, the persistence layer, the device-locale fallback below)
/// works off it rather than a hardcoded switch per language.
const supportedLanguageCodes = ['sw', 'en'];

/// Each language's name in its OWN script (Part 2, client feedback:
/// "Kiswahili — ... — 'Kiswahili'; English — ... — 'English'") — always
/// the endonym, never translated into whichever language is currently
/// active, so a Kiswahili speaker sees "English" (not "Kiingereza") and
/// vice versa. The minimal settings row Part 1 ships uses this; Part
/// 2's flag/dropdown/bottom-sheet picker reuses it rather than
/// duplicating the names.
const languageEndonyms = {'sw': 'Kiswahili', 'en': 'English'};

/// Part 1 (language audit, client feedback): "Default to Kiswahili when
/// the device locale isn't explicitly English... do not default to
/// English just because a browser is configured in the United States"
/// — the exact same rule the website's SetWebLocale middleware
/// implements, applied here to the device's own preferred-locale list
/// instead of an Accept-Language header. Deliberately not left to
/// Flutter's own default resolution (which falls back to the FIRST
/// entry of `supportedLocales` on no match) — that fallback depends on
/// ARB-file discovery order, which is fragile and not what "explicitly
/// English" means.
Locale resolveDeviceLocale(List<Locale>? deviceLocales) {
  final hasEnglish = (deviceLocales ?? const []).any((l) => l.languageCode == 'en');
  return Locale(hasEnglish ? 'en' : 'sw');
}

/// Part 1 (language audit, client feedback): "Persist the choice across
/// restarts and across account switches." A device-wide preference,
/// deliberately NOT scoped by active user id the way search
/// history/drafts/seen-marks are (see `scopedCacheKey()` in
/// core/providers.dart) — switching accounts must never reset it, so it
/// lives in the same unscoped key/value row regardless of who's signed
/// in, and survives `AuthStateController.signOut()`'s
/// `appDatabaseProvider.clearAll()` because that only ever touches the
/// cached-listings tables, never this generic key/value one.
class LocaleController extends AsyncNotifier<Locale?> {
  @override
  Future<Locale?> build() async {
    final saved = await ref.watch(appDatabaseProvider).getKeyValue(_localeKey);
    return (saved != null && supportedLanguageCodes.contains(saved)) ? Locale(saved) : null;
  }

  /// A real explicit choice always wins over the device-based default —
  /// see `resolveDeviceLocale()`, used only while this is null.
  Future<void> setLocale(String languageCode) async {
    await ref.read(appDatabaseProvider).setKeyValue(_localeKey, languageCode);
    state = AsyncData(Locale(languageCode));
  }
}

final localeControllerProvider = AsyncNotifierProvider<LocaleController, Locale?>(LocaleController.new);
