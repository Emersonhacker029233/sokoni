# Blockers

Things that need input from the project owner. Each is stubbed so the build keeps moving; nothing here halts development.

## Awaiting credentials (the four allowed stop-and-ask items)

1. **Firebase project** — need `google-services.json` (Android) and `GoogleService-Info.plist` (iOS).
   - Stub: `app/lib/core/push/push_service.dart` is the real, complete client-side push registration flow (requests notification permission, gets an FCM token, POSTs it to `/devices`, re-registers on token refresh) triggered right after every successful sign-in (`AuthStateController` in `app/lib/core/providers.dart`). With no config files present, `Firebase.initializeApp()` throws immediately (no native platform default to fall back to) — caught there, logged, and the rest of the app keeps working normally (chat's 5s polling doesn't depend on push at all). Server-side, `LogPushNotifier` already logs every push that would have been sent (new order, status change) — see `api/app/Services/Push/`. Dropping the two config files into `app/android/app/` and `app/ios/Runner/`, adding the Google Services Gradle plugin to `android/build.gradle`/`android/app/build.gradle`, and swapping `LogPushNotifier` for a `kreait/laravel-firebase`-backed implementation is the entire remaining integration — no Dart code changes needed.
2. **Google Maps API key** (Android + iOS, needs SHA-1 fingerprint for Android).
   - Stub: `MAPS_API_KEY=REPLACE_ME` placeholder wired into Android `local.properties`-style config and iOS `AppDelegate`; map screens render a "map unavailable — add API key" fallback state rather than crashing.
3. **Google OAuth server client ID, Facebook App ID, Apple Sign-In Service ID**.
   - Stub: `google_sign_in`, `flutter_facebook_auth` and `sign_in_with_apple` are fully integrated in `features/auth/presentation/social_sign_in_buttons.dart` — real native SDK flows, tokens verified server-side in `HttpSocialAuthVerifier` (never trusted client-side). Google's and Apple's buttons check `SokoniSocialAuthConfig` (`app/lib/core/config/social_auth_config.dart`, values read via `--dart-define`) and disable themselves with a tooltip while their ID is still the `REPLACE_ME` placeholder, since attempting either flow without a real ID fails inside the native SDK rather than failing cleanly. Facebook's App ID lives in native platform config (`AndroidManifest.xml`/`Info.plist`, not Dart) per `flutter_facebook_auth`'s own setup — those platform files still need the real App ID dropped in, but there's no Dart-side gate for it. Server-side, `services.google.client_id` / `services.facebook.client_id`+`client_secret` / `services.apple.client_id` in `api/.env` gate the same three flows — see `api/.env.example`.
4. **Production DB credentials for `sokoni.co.tz`** (cPanel MySQL).
   - Stub: `api/.env.example` documents every required var with placeholder values; local dev uses SQLite/local MySQL instead.

## Non-credential blockers encountered

- `winget install PHP.PHP.8.3` failed (upstream 404 on that specific installer archive). Resolved by using the pre-installed Laravel Herd toolchain (PHP 8.4) instead — see `DECISIONS.md`. No action needed from the project owner.
