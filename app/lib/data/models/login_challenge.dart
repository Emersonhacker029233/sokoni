import 'auth_response.dart';

/// Result of [AuthRepository.login] — a plain (non-freezed) class since
/// it's a two-shape union over an already-typed [AuthResponse], not worth
/// its own generated code. Exactly one of [response]/[codeExpiresAt] is
/// ever non-null, matching the server's own two response shapes
/// (CLAUDE.md Part D 2.2: a recognised device with no 2FA signs in
/// immediately; anything else requires the SMS-code step next).
class LoginChallenge {
  const LoginChallenge.signedIn(AuthResponse this.response) : codeExpiresAt = null;

  const LoginChallenge.requiresCode(DateTime this.codeExpiresAt) : response = null;

  final AuthResponse? response;
  final DateTime? codeExpiresAt;

  bool get requiresCode => codeExpiresAt != null;
}
