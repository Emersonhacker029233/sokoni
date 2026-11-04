/// Single source of truth for the Terms & Privacy version string sent to
/// `POST /auth/terms/accept` — bump this whenever the content below
/// changes materially, so `terms_version` on the user record stays a
/// meaningful audit trail (CLAUDE.md feature 11: "acceptance recorded
/// (timestamp + version) at signup").
///
/// 1.1 (Apple Guideline 1.2, build 1.0 (9)): adds the zero-tolerance
/// clause, report and block, and the 24-hour review promise. Every account
/// on 1.0 is asked once (see `ensureTermsAccepted`). The website's
/// `App\Support\Legal::TERMS_VERSION` must match.
const sokoniTermsVersion = '1.1';
