# Blockers

Things that need input from the project owner. Each is stubbed so the build keeps moving; nothing here halts development.

## Awaiting credentials (the four allowed stop-and-ask items)

1. **Firebase project** — need `google-services.json` (Android) and `GoogleService-Info.plist` (iOS).
   - Stub: Firebase is initialized behind a guarded `firebase_options.dart` that reads placeholder values; push notification code paths are implemented against `firebase_messaging` but will no-op gracefully until real config files are dropped in `app/android/app/` and `app/ios/Runner/`.
2. **Google Maps API key** (Android + iOS, needs SHA-1 fingerprint for Android).
   - Stub: `MAPS_API_KEY=REPLACE_ME` placeholder wired into Android `local.properties`-style config and iOS `AppDelegate`; map screens render a "map unavailable — add API key" fallback state rather than crashing.
3. **Facebook App ID + Apple Sign-In Service ID**.
   - Stub: `flutter_facebook_auth` and `sign_in_with_apple` are integrated behind the same auth interface as Google/phone; buttons are present but disabled with a tooltip until IDs are supplied in `.env` / platform config.
4. **Production DB credentials for `sokoni.co.tz`** (cPanel MySQL).
   - Stub: `api/.env.example` documents every required var with placeholder values; local dev uses SQLite/local MySQL instead.

## Non-credential blockers encountered

- `winget install PHP.PHP.8.3` failed (upstream 404 on that specific installer archive). Resolved by using the pre-installed Laravel Herd toolchain (PHP 8.4) instead — see `DECISIONS.md`. No action needed from the project owner.
