<?php

namespace App\Filament\Resources\SellerProfiles\Pages;

use App\Filament\Resources\SellerProfiles\SellerProfileResource;
use Filament\Resources\Pages\ViewRecord;

class ViewSellerProfile extends ViewRecord
{
    protected static string $resource = SellerProfileResource::class;

    /**
     * Verify/reject must live here, not just on the list table — this is
     * the page that actually shows the NIDA photo, licence and map a
     * reviewer needs to decide (see SellerProfileInfolist). Without this,
     * the only page with the evidence had no way to act on it — see
     * SellerProfileResource::verifyAction()'s docblock for how this was
     * diagnosed.
     */
    protected function getHeaderActions(): array
    {
        return [
            SellerProfileResource::verifyAction(),
            SellerProfileResource::rejectAction(),
        ];
    }
}
