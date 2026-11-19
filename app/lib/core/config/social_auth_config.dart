import 'package:flutter/foundation.dart';

/// Google and Apple sign-in (Facebook was removed entirely, not just left
/// unconfigured; see BLOCKERS.md and DECISIONS.md).
///
/// **Android shows both, iOS shows neither.** Android 1.1 offers Google
/// and Apple; iOS stays exactly as the reviewed 1.0 (9): phone and
/// password only. The platform decides this, not the build command, so
/// an iOS build can't pick the buttons up by accident and an Android
/// release build needs no `--dart-define` to show them.
///
/// The sign-in *code paths* are fully real (real SDK calls, real tokens,
/// real POSTs to `/auth/social`, verified server-side). This class only
/// decides whether each button *renders at all*: a provider that can't
/// work is omitted entirely, never shown disabled (Apple App Review's
/// Guideline 2.1(a) rejection: a disabled button reads as "unresponsive"
/// on a touch device with no hover state).
///
/// The defaults are public identifiers, not secrets: an OAuth client ID
/// and an Apple Service ID ship inside every app that uses them. A
/// `--dart-define` still overrides either one, and setting it to
/// `REPLACE_ME` hides that button.
abstract final class SokoniSocialAuthConfig {
  /// The **Web application**-type OAuth client (Firebase's `client_type: 3`
  /// in `google-services.json`), never the iOS or Android one. It must
  /// match `GOOGLE_CLIENT_ID` in `api/.env` or the server's audience check
  /// fails. See docs/DEPLOY.md.
  static const googleServerClientId = String.fromEnvironment(
    'GOOGLE_SERVER_CLIENT_ID',
    defaultValue: '825257290618-pis0jcmn11bo5n3vpi5q0nchagrlhbuq.apps.googleusercontent.com',
  );

  /// The Apple Service ID. Android's web flow mints tokens for this
  /// audience, so it must match `APPLE_SERVICE_ID` in `api/.env`.
  static const appleServiceId = String.fromEnvironment(
    'APPLE_SERVICE_ID',
    defaultValue: 'tz.co.sokoni.signin',
  );

  /// Where Apple posts the Android web flow's result. It must be listed
  /// under the Service ID's Return URLs in Apple Developer, and it is
  /// served by `AppleSignInCallbackController`.
  static const appleRedirectUri = String.fromEnvironment(
    'APPLE_REDIRECT_URI',
    defaultValue: 'https://sokoni.co.tz/auth/apple/callback',
  );

  /// Only Android offers Google and Apple sign-in.
  static bool get platformOffersSocialSignIn => defaultTargetPlatform == TargetPlatform.android;

  static bool get isGoogleConfigured => platformOffersSocialSignIn && !googleServerClientId.startsWith('REPLACE_ME');

  static bool get isAppleConfigured => platformOffersSocialSignIn && !appleServiceId.startsWith('REPLACE_ME');

  /// Whether the "or continue with" divider above phone sign-in has
  /// anything to divide. The divider and the whole social section are
  /// left out together when no provider shows.
  static bool get anySocialConfigured => isGoogleConfigured || isAppleConfigured;
}
