<?php

namespace App\Filament\Resources\ReturnRequests\Pages;

use App\Filament\Resources\ReturnRequests\ReturnRequestResource;
use Filament\Resources\Pages\ViewRecord;

class ViewReturnRequest extends ViewRecord
{
    protected static string $resource = ReturnRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ReturnRequestResource::acceptAction(),
            ReturnRequestResource::closeAction(),
            ReturnRequestResource::payerAction(),
            ReturnRequestResource::markRideFeeRefundedAction(),
            ReturnRequestResource::hideAction(),
        ];
    }
}
