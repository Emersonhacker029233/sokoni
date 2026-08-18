<?php

namespace App\Filament\Resources\Offers\Tables;

use App\Models\Offer;
use App\Support\ActivityLogger;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class OffersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('starts_at', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['seller', 'product']))
            ->columns([
                TextColumn::make('product.title')
                    ->label('Product')
                    ->searchable(),
                TextColumn::make('seller.shop_name')
                    ->label('Seller')
                    ->searchable(),
                TextColumn::make('discount')
                    ->label('Discount')
                    ->state(fn (Offer $record) => $record->discount_type === 'percent'
                        ? "{$record->discount_value}% off"
                        : 'TSh '.number_format((float) $record->discount_value)),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->state(fn (Offer $record) => match (true) {
                        $record->starts_at->isFuture() => 'Upcoming',
                        $record->isActive() => 'Active',
                        default => 'Ended',
                    })
                    ->color(fn (string $state) => match ($state) {
                        'Active' => 'success',
                        'Upcoming' => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('ends_at')
                    ->label('Ends')
                    ->state(fn (Offer $record) => $record->isActive() ? $record->ends_at->diffForHumans() : $record->ends_at->format('d M Y, H:i')),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(['active' => 'Active', 'upcoming' => 'Upcoming', 'ended' => 'Ended'])
                    ->query(function (Builder $query, array $data): Builder {
                        return match ($data['value'] ?? null) {
                            'active' => $query->where('starts_at', '<=', now())->where('ends_at', '>=', now()),
                            'upcoming' => $query->where('starts_at', '>', now()),
                            'ended' => $query->where('ends_at', '<', now()),
                            default => $query,
                        };
                    }),
            ])
            ->recordActions([
                Action::make('remove')
                    ->label('Remove')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function (Offer $record) {
                        ActivityLogger::record(Auth::user(), 'offer.removed', $record, "On \"{$record->product?->title}\"");
                        $record->delete();
                        Notification::make()->title('Offer removed')->success()->send();
                    }),
            ])
            ->toolbarActions([])
            ->defaultPaginationPageOption(25);
    }
}
