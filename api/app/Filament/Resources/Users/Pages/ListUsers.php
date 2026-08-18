<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Support\CsvExporter;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportCsv')
                ->label('Export CSV')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->action(fn () => CsvExporter::stream(
                    'users-'.now()->format('Y-m-d-His').'.csv',
                    ['ID', 'Name', 'Phone', 'Email', 'Is seller', 'Is admin', 'Banned', 'Joined'],
                    CsvExporter::cursorRows(
                        $this->getFilteredSortedTableQuery()->cursor(),
                        fn ($user) => [
                            $user->id,
                            $user->name,
                            $user->phone,
                            $user->email,
                            $user->isSeller() ? 'Yes' : 'No',
                            $user->is_admin ? 'Yes' : 'No',
                            $user->isBanned() ? 'Yes' : 'No',
                            $user->created_at?->toDateTimeString(),
                        ],
                    ),
                )),
            CreateAction::make(),
        ];
    }
}
