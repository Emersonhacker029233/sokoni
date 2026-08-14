/// Single source of truth for the Terms & Privacy version string sent to
/// `POST /auth/terms/accept` — bump this whenever the content below
/// changes materially, so `terms_version` on the user record stays a
/// meaningful audit trail (CLAUDE.md feature 11: "acceptance recorded
/// (timestamp + version) at signup").
const sokoniTermsVersion = '1.0';
