<?php

namespace App\Filament\Resources\Drivers\Pages;

use App\Filament\Resources\Drivers\DriverResource;
use Filament\Resources\Pages\ViewRecord;

/** The review screen: all the evidence, with the decisions at the top. */
class ViewDriver extends ViewRecord
{
    protected static string $resource = DriverResource::class;

    public function getTitle(): string
    {
        return 'Driver: '.($this->getRecord()->user?->name ?? '#'.$this->getRecord()->getKey());
    }

    protected function getHeaderActions(): array
    {
        return [
            DriverResource::approveAction(),
            DriverResource::rejectAction(),
            DriverResource::suspendAction(),
            DriverResource::reinstateAction(),
            DriverResource::unlockPinAction(),
        ];
    }
}
