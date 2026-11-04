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
    public const TERMS_VERSION = '1.1';
}
