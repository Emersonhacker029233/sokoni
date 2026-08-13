/// Credentials for the three social sign-in providers (CLAUDE.md's third
/// "stop and ask" blocker: "Facebook App ID and Apple Sign-In service ID" —
/// Google's server client ID is the equivalent piece for Google Sign-In's
/// ID-token flow). See BLOCKERS.md.
///
/// The sign-in *code paths* below are fully real — real SDK calls, real
/// tokens, real POSTs to `/auth/social` — this module only gates whether
/// each button is enabled, since attempting a provider flow with a
/// placeholder ID fails or hangs in the native SDK rather than failing
/// cleanly. Replace these placeholders and every provider lights up with
/// no other code changes.
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

  // Facebook's App ID lives in native config (AndroidManifest.xml /
  // Info.plist), not Dart, on mobile — flutter_facebook_auth reads it from
  // there automatically. There's nothing to check for a placeholder client
  // side, so this stays true; a missing native App ID surfaces as a normal
  // provider-side login failure instead, same as any other transient
  // Facebook login error.
  static bool get isFacebookConfigured => true;

  static bool get isAppleConfigured => !appleServiceId.startsWith('REPLACE_ME');
}
