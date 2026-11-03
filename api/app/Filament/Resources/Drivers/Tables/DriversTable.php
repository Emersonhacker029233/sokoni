<?php

namespace App\Filament\Resources\Drivers\Tables;

use App\Filament\Resources\Drivers\DriverResource;
use App\Models\DriverProfile;
use App\Services\Delivery\DriverReviewService;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * The driver review queue. Opens on "Pending review", oldest submission
 * first, so a reviewer works down the longest wait. Decisions happen on
 * the review page, where the documents are, not from this list.
 */
class DriversTable
{
    public const FILTERS = [
        'review' => 'Pending review',
        'draft' => 'Not submitted yet',
        'approved' => 'Approved',
        'rejected' => 'Rejected',
        'suspended' => 'Suspended',
    ];

    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('submitted_at', 'asc')
            ->recordUrl(fn (DriverProfile $record) => DriverResource::getUrl('view', ['record' => $record]))
            ->columns([
                TextColumn::make('user.name')
                    ->label('Driver')
                    ->searchable(),
                TextColumn::make('user.phone')
                    ->label('Phone')
                    ->searchable()
                    ->copyable(),
                TextColumn::make('vehicle')
                    ->label('Vehicle')
                    ->state(fn (DriverProfile $record) => $record->activeVehicle === null
                        ? 'None yet'
                        : ucfirst(str_replace('_', ' ', $record->activeVehicle->type)))
                    ->description(fn (DriverProfile $record) => $record->activeVehicle?->plate_number),
                TextColumn::make('review_state')
                    ->label('Status')
                    ->badge()
                    ->state(fn (DriverProfile $record) => self::stateLabel($record))
                    ->color(fn (DriverProfile $record) => match (true) {
                        DriverReviewService::needsReview($record) => 'warning',
                        $record->status === 'approved' => 'success',
                        $record->status === 'rejected', $record->status === 'suspended' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('submitted_at')
                    ->label('Submitted')
                    ->since()
                    ->dateTimeTooltip()
                    ->sortable()
                    ->placeholder('Not submitted'),
                IconColumn::make('documents_complete')
                    ->label('Complete')
                    ->boolean()
                    ->state(fn (DriverProfile $record) => $record->missingRequirements() === []),
                TextColumn::make('flag_reason')
                    ->label('Flag')
                    ->color('danger')
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('queue')
                    ->label('Status')
                    ->options(self::FILTERS)
                    ->default('review')
                    ->query(fn (Builder $query, array $data) => match ($data['value'] ?? null) {
                        'review' => DriverResource::reviewQueue($query),
                        'draft' => $query->where('status', 'pending')->whereNull('submitted_at'),
                        'approved', 'rejected', 'suspended' => $query->where('status', $data['value']),
                        default => $query,
                    }),
            ])
            ->recordActions([
                ViewAction::make()->label('Review'),
            ])
            ->emptyStateIcon('heroicon-o-check-badge')
            ->emptyStateHeading('No drivers here')
            ->emptyStateDescription('Nobody is waiting for review. Change the Status filter to see other drivers.');
    }

    public static function stateLabel(DriverProfile $record): string
    {
        return match (true) {
            DriverReviewService::isVehicleCheck($record) => 'New vehicle to check',
            DriverReviewService::isApplication($record) => 'Pending review',
            $record->status === 'pending' => 'Not submitted',
            default => ucfirst($record->status),
        };
    }
}
