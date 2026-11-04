<?php

namespace App\Filament\Resources\Reports\Tables;

use App\Filament\Resources\Reports\ReportActions;
use App\Models\Report;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * The moderation queue (CLAUDE.md feature 9/11, Apple Guideline 1.2).
 * Pending reports come first, oldest first, each with how long is left of
 * its 24 hours; overdue ones show red and have their own filter.
 * Resolving a report as "upheld" also feeds `ReportObserver`'s auto-hide.
 */
class ReportsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['reporter', 'reportable']))
            ->defaultSort(fn (Builder $query) => $query
                ->orderByRaw("case when status = 'pending' then 0 else 1 end")
                ->orderByRaw("case when status = 'pending' then created_at end asc")
                ->orderByDesc('created_at'))
            ->columns([
                TextColumn::make('due')
                    ->label('Due')
                    ->badge()
                    ->state(fn (Report $record) => $record->dueLabel())
                    ->color(fn (Report $record) => $record->dueColor())
                    ->icon(fn (Report $record) => $record->isOverdue() ? 'heroicon-o-exclamation-circle' : null),
                TextColumn::make('reportable_type')
                    ->label('Type')
                    ->formatStateUsing(fn (Report $record) => ucfirst($record->typeName())),
                TextColumn::make('content')
                    ->label('Content')
                    ->state(fn (Report $record) => Str::limit($record->contentPreview()['text'] ?? $record->contentPreview()['title'], 60))
                    ->wrap(),
                TextColumn::make('reason')
                    ->formatStateUsing(fn (Report $record) => $record->reasonLabel())
                    ->searchable(),
                TextColumn::make('reporter.name')
                    ->label('Reported by')
                    ->searchable(),
                TextColumn::make('author')
                    ->label('Posted by')
                    ->state(fn (Report $record) => $record->offendingUser()?->name ?? '-'),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'upheld' => 'danger',
                        'dismissed' => 'gray',
                        default => 'warning',
                    }),
                TextColumn::make('created_at')
                    ->label('Reported')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Filter::make('overdue')
                    ->label('Overdue (pending over 24 hours)')
                    ->toggle()
                    ->query(fn (Builder $query) => $query->overdue()),
                SelectFilter::make('status')->options([
                    'pending' => 'Pending',
                    'upheld' => 'Upheld',
                    'dismissed' => 'Dismissed',
                ]),
                SelectFilter::make('reportable_type')
                    ->label('Type')
                    ->options(collect(Report::TYPES)->mapWithKeys(fn (string $class, string $name) => [$class => ucfirst($name)])->all()),
            ])
            ->recordActions([
                ViewAction::make(),
                ActionGroup::make(ReportActions::all()),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
