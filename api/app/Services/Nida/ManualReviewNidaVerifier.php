<?php

namespace App\Services\Nida;

/**
 * MOCK: live NIDA API verification requires a government data-sharing
 * agreement that isn't in place (see BLOCKERS.md / CLAUDE.md feature 4).
 * The number and ID photo are already persisted onto the seller profile by
 * the onboarding controller before this runs — this implementation is
 * intentionally a no-op that just confirms receipt; a human reviewer makes
 * the actual verify/reject decision via the Filament seller queue. Swap
 * this binding in AppServiceProvider for a real NIDA-backed implementation
 * once that agreement exists.
 */
class ManualReviewNidaVerifier implements NidaVerifier
{
    public function submit(string $nidaNumber, string $nidaImagePath): NidaVerificationResult
    {
        return NidaVerificationResult::pendingManualReview();
    }
}
