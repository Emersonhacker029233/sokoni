<?php

namespace App\Filament\Resources\ActivityLogs\Tables;

use App\Models\ActivityLog;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Infolists\Components\TextEntry;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ActivityLogsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label('When')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('causer.name')
                    ->label('Who')
                    ->searchable()
                    ->placeholder('System'),
                TextColumn::make('action')
                    ->label('What')
                    ->badge()
                    ->searchable(),
                TextColumn::make('subject_type')
                    ->label('Subject')
                    ->formatStateUsing(fn (ActivityLog $record) => $record->subject_type
                        ? class_basename($record->subject_type).' #'.$record->subject_id
                        : '—'),
                TextColumn::make('reason')
                    ->label('Why')
                    ->limit(50)
                    ->placeholder('—')
                    ->wrap(),
            ])
            ->filters([
                SelectFilter::make('causer_id')
                    ->label('Who')
                    ->relationship('causer', 'name')
                    ->searchable(),
                Filter::make('created_at')
                    ->schema([
                        DatePicker::make('from'),
                        DatePicker::make('until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'] ?? null, fn (Builder $q, $date) => $q->whereDate('created_at', '>=', $date))
                            ->when($data['until'] ?? null, fn (Builder $q, $date) => $q->whereDate('created_at', '<=', $date));
                    }),
            ])
            ->recordActions([
                Action::make('details')
                    ->label('Details')
                    ->icon('heroicon-o-magnifying-glass')
                    ->modalSubmitAction(false)
                    ->modalCancelAction(false)
                    ->infolist([
                        TextEntry::make('created_at')->label('When')->dateTime(),
                        TextEntry::make('causer.name')->label('Who')->placeholder('System'),
                        TextEntry::make('action')->label('What')->badge(),
                        TextEntry::make('subject_type')->label('Subject')->formatStateUsing(
                            fn (ActivityLog $record) => $record->subject_type
                                ? class_basename($record->subject_type).' #'.$record->subject_id
                                : '—'
                        ),
                        TextEntry::make('reason')->label('Why')->placeholder('—')->columnSpanFull(),
                        TextEntry::make('meta')
                            ->label('Extra detail')
                            ->placeholder('—')
                            ->formatStateUsing(fn (?array $state) => $state ? json_encode($state, JSON_PRETTY_PRINT) : null)
                            ->columnSpanFull(),
                    ]),
            ])
            ->toolbarActions([])
            ->defaultPaginationPageOption(25);
    }
}
