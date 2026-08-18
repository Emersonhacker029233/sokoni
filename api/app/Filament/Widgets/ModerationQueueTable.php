<?php

namespace App\Filament\Widgets;

use App\Models\Report;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/** "The moderation queue as actionable lists, each row linking straight to the item" (CLAUDE.md admin rebuild, Section 5). */
class ModerationQueueTable extends TableWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = ['md' => 1];

    public function table(Table $table): Table
    {
        return $table
            ->heading('Moderation queue')
            ->query(Report::query()->where('status', 'pending')->with('reporter')->oldest()->limit(10))
            ->paginated(false)
            ->recordUrl(fn (Report $record) => route('filament.admin.resources.reports.view', $record))
            ->columns([
                TextColumn::make('reportable_type')->label('Type')->formatStateUsing(fn (string $state) => class_basename($state)),
                TextColumn::make('reason')->badge(),
                TextColumn::make('reporter.name')->label('Reported by'),
                TextColumn::make('created_at')->label('Waiting')->since(),
            ])
            ->emptyStateHeading('Queue is clear')
            ->emptyStateDescription('No pending reports.');
    }
}
