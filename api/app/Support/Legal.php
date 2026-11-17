<?php

namespace App\Support;

/**
 * Mirrors `app/lib/features/legal/legal_content.dart`'s `sokoniTermsVersion`
 * exactly — CLAUDE.md's website brief: "Terms acceptance recorded exactly
 * as the app does, same version constant." The API itself accepts
 * whatever version string a client sends (`POST /auth/terms/accept`), so
 * this constant's only job is making sure the *website* sends the same
 * value the app does, not a second, driftable "1.0" typed independently.
 * Bump both together if the legal text changes materially.
 */
class Legal
{
    // 1.1 (Apple Guideline 1.2): adds the zero-tolerance clause, report
    // and block, and the 24-hour review promise. Every account accepts it
    // once, on the website and in the app.
    //
    // Deliberately NOT bumped for the Return Policy: iOS 1.0 (9) asks again
    // whenever users.terms_version differs from its own "1.1", and it has
    // no Return Policy text to show. See RETURNS_TERMS_VERSION.
    public const TERMS_VERSION = '1.1';

    // Terms 1.2 = 1.1 plus the Return Policy section (docs/delivery/RETURNS.md).
    // Shown on the Terms page while DELIVERY_RETURNS_ENABLED is on, and agreed
    // to in the new app before a user's first return or Bolt delivery. Kept in
    // users.returns_terms_version, never in users.terms_version, so no build
    // that predates returns is ever asked to re-accept anything.
    public const RETURNS_TERMS_VERSION = '1.2';

    /** The version printed on the Terms page right now. */
    public static function displayedTermsVersion(): string
    {
        return config('delivery.returns_enabled') ? self::RETURNS_TERMS_VERSION : self::TERMS_VERSION;
    }
}
