<?php

namespace App\Filament\Resources\Reports\Pages;

use App\Filament\Resources\Reports\ReportActions;
use App\Filament\Resources\Reports\ReportResource;
use Filament\Actions\ActionGroup;
use Filament\Resources\Pages\ViewRecord;

/** A single report with its content shown in full, and the same moderation actions as the list. */
class ViewReport extends ViewRecord
{
    protected static string $resource = ReportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ReportActions::removeAndBan(),
            ReportActions::hide(),
            ReportActions::dismiss(),
            ActionGroup::make([
                ReportActions::unhide(),
                ReportActions::uphold(),
                ReportActions::warn(),
                ReportActions::suspend(),
                ReportActions::ban(),
                ReportActions::removeLogo(),
                ReportActions::clearBio(),
                ReportActions::removeAvatar(),
            ])->label('More')->button(),
        ];
    }
}
