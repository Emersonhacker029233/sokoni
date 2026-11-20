<?php

namespace App\Filament\Resources\BoltDeliveries\Pages;

use App\Filament\Resources\BoltDeliveries\BoltDeliveryResource;
use App\Filament\Support\PaymentAttempts;
use App\Models\DeliveryJob;
use Filament\Resources\Pages\ViewRecord;

class ViewBoltDelivery extends ViewRecord
{
    protected static string $resource = BoltDeliveryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            BoltDeliveryResource::recordFeePaidAction(),
            BoltDeliveryResource::markRefundedAction(),
            PaymentAttempts::markRefundedAction(fn (DeliveryJob $record) => $record->payments()->get()),
            BoltDeliveryResource::cancelAction(),
        ];
    }
}
