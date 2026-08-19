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
    public const TERMS_VERSION = '1.0';
}
