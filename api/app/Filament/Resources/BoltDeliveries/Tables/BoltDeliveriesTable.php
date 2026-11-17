<?php

namespace App\Filament\Resources\BoltDeliveries\Tables;

use App\Filament\Resources\BoltDeliveries\BoltDeliveryResource;
use App\Models\DeliveryJob;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class BoltDeliveriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['order.buyer', 'order.seller']))
            ->columns([
                TextColumn::make('order.code')->label('Order')->searchable(),
                TextColumn::make('order.seller.shop_name')->label('Shop')->searchable(),
                TextColumn::make('order.buyer.name')->label('Buyer'),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'delivered' => 'success',
                        'failed', 'no_driver_found', 'cancelled' => 'danger',
                        'pending_payment', 'pending_quote' => 'gray',
                        default => 'warning',
                    }),
                TextColumn::make('provider_state')->label('Bolt state')->placeholder('—'),
                TextColumn::make('fee')->label('Customer paid')->money('TZS', divideBy: 1)->sortable(),
                TextColumn::make('final_cost')->label('Bolt charged')->money('TZS', divideBy: 1)->placeholder('—')->sortable(),
                TextColumn::make('cost_difference')
                    ->label('Difference')
                    ->money('TZS', divideBy: 1)
                    ->placeholder('—')
                    ->color(fn (?float $state) => $state !== null && $state < 0 ? 'danger' : null)
                    ->sortable(),
                TextColumn::make('refund_status')->label('Refund')->badge()->placeholder('—'),
                TextColumn::make('provider_ref')->label('Bolt ride')->placeholder('—')->searchable(),
                TextColumn::make('created_at')->label('Accepted')->dateTime()->sortable(),
            ])
            ->filters([
                Filter::make('attention')
                    ->label('Needs attention')
                    ->toggle()
                    ->query(fn (Builder $query) => BoltDeliveryResource::needsAttention($query)),
                Filter::make('overrun')
                    ->label('Bolt charged more than the customer paid')
                    ->toggle()
                    ->query(fn (Builder $query) => $query->where('cost_difference', '<', 0)),
                SelectFilter::make('status')->options(array_combine(DeliveryJob::STATUSES, DeliveryJob::STATUSES)),
            ])
            ->recordActions([ViewAction::make()]);
    }
}
