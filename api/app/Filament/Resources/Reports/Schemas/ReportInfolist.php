<?php

namespace App\Filament\Resources\Reports\Schemas;

use App\Models\Report;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class ReportInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('reporter.name')
                    ->label('Reporter'),
                TextEntry::make('reportable_type')
                    ->label('Content type')
                    ->formatStateUsing(fn (string $state) => class_basename($state)),
                TextEntry::make('reportable_id')
                    ->label('Content ID')
                    ->numeric(),
                TextEntry::make('offendingUserName')
                    ->label('User')
                    ->state(fn (Report $record) => $record->offendingUser()?->name ?? '-'),
                TextEntry::make('reason'),
                TextEntry::make('note')
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('status')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'upheld' => 'danger',
                        'dismissed' => 'gray',
                        default => 'warning',
                    }),
                TextEntry::make('resolvedBy.name')
                    ->label('Resolved by')
                    ->placeholder('-'),
                TextEntry::make('resolution')
                    ->placeholder('-'),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
            ]);
    }
}
