/// Whether the Google Maps API key is configured for this build (CLAUDE.md's
/// second "stop and ask" blocker — see BLOCKERS.md).
///
/// The key itself lives entirely in native config —
/// `android/app/src/main/AndroidManifest.xml`'s `com.google.android.geo.API_KEY`
/// meta-data and `ios/Runner/AppDelegate.swift`'s `GMSServices.provideAPIKey(...)`
/// — `GoogleMap` takes no key parameter, and there's no programmatic way to
/// ask the native SDK whether it actually has a working one. A missing or
/// placeholder key fails silently inside the SDK: blank grey tiles, nothing
/// surfaced to Dart, no exception to catch.
///
/// [isConfigured] is a manually-maintained mirror of that native placeholder,
/// so the UI can show a real "map unavailable" message instead of a blank
/// map. When a real key replaces `REPLACE_ME` in both native files, flip
/// this to `true` too — the three must be updated together, there's no
/// single source of truth to read from at build time.
abstract final class SokoniMapsConfig {
  static const isConfigured = false;
}
