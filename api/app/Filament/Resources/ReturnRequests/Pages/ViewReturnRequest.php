<?php

namespace App\Filament\Resources\ReturnRequests\Pages;

use App\Filament\Resources\ReturnRequests\ReturnRequestResource;
use App\Filament\Support\PaymentAttempts;
use App\Models\ReturnRequest;
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
            ReturnRequestResource::recordRideFeePaidAction(),
            ReturnRequestResource::markRideFeeRefundedAction(),
            PaymentAttempts::markRefundedAction(fn (ReturnRequest $record) => $record->payments()->get()),
            ReturnRequestResource::hideAction(),
        ];
    }
}
