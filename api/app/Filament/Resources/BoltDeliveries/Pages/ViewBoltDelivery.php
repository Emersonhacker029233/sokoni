<?php

namespace App\Filament\Resources\BoltDeliveries\Pages;

use App\Filament\Resources\BoltDeliveries\BoltDeliveryResource;
use Filament\Resources\Pages\ViewRecord;

class ViewBoltDelivery extends ViewRecord
{
    protected static string $resource = BoltDeliveryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            BoltDeliveryResource::recordFeePaidAction(),
            BoltDeliveryResource::markRefundedAction(),
            BoltDeliveryResource::cancelAction(),
        ];
    }
}
