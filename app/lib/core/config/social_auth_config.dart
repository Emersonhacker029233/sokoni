/// Credentials for the remaining social sign-in providers (CLAUDE.md's
/// third "stop and ask" blocker originally also covered a Facebook App
/// ID — Facebook sign-in was removed entirely, not just left unconfigured;
/// see BLOCKERS.md and DECISIONS.md. Google's server client ID is the
/// equivalent piece for Google Sign-In's ID-token flow).
///
/// The sign-in *code paths* below are fully real — real SDK calls, real
/// tokens, real POSTs to `/auth/social` — this module only gates whether
/// each button *renders at all* (`SocialSignInButtons` — a provider whose
/// credential is still a placeholder is omitted entirely, not shown
/// disabled, per Apple App Review's Guideline 2.1(a) rejection: a button
/// that cannot work must not render, since even a disabled button with a
/// tooltip reads as "unresponsive" on a touch device with no hover state).
/// Replace these placeholders and every provider lights up with no other
/// code changes.
abstract final class SokoniSocialAuthConfig {
  static const googleServerClientId = String.fromEnvironment(
    'GOOGLE_SERVER_CLIENT_ID',
    defaultValue: 'REPLACE_ME.apps.googleusercontent.com',
  );

  static const appleServiceId = String.fromEnvironment(
    'APPLE_SERVICE_ID',
    defaultValue: 'REPLACE_ME',
  );

  static const appleRedirectUri = String.fromEnvironment(
    'APPLE_REDIRECT_URI',
    defaultValue: 'https://sokoni.co.tz/auth/apple/callback',
  );

  static bool get isGoogleConfigured => !googleServerClientId.startsWith('REPLACE_ME');

  static bool get isAppleConfigured => !appleServiceId.startsWith('REPLACE_ME');

  /// Whether the "or continue with" divider above phone sign-in has
  /// anything to divide — both the divider and the whole social section
  /// are omitted together when neither provider is configured, rather
  /// than leaving a divider above an empty gap.
  static bool get anySocialConfigured => isGoogleConfigured || isAppleConfigured;
}
