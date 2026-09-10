<?php

namespace App\Services\Nida;

interface NidaVerifier
{
    /**
     * Submit a seller's NIDA number for verification — B1 (tester
     * feedback): no supporting ID photo is collected at all anymore, the
     * typed number is the whole submission. Returns a status the seller
     * onboarding flow can show immediately; the actual decision happens
     * out-of-band (see NidaVerificationResult).
     */
    public function submit(string $nidaNumber): NidaVerificationResult;
}
