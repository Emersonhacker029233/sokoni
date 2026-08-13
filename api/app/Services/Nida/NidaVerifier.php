<?php

namespace App\Services\Nida;

interface NidaVerifier
{
    /**
     * Submit a seller's NIDA number + ID photo for verification. Returns a
     * status the seller onboarding flow can show immediately; the actual
     * decision happens out-of-band (see NidaVerificationResult).
     */
    public function submit(string $nidaNumber, string $nidaImagePath): NidaVerificationResult;
}
