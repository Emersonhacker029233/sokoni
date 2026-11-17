<?php

namespace App\Filament\Resources\ReturnRequests\Tables;

use App\Filament\Resources\ReturnRequests\ReturnRequestResource;
use App\Models\ReturnRequest;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ReturnRequestsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['order', 'buyer', 'shop']))
            ->defaultSort(fn (Builder $query) => $query
                ->orderByRaw("case when status = 'disputed' or (status = 'requested' and escalated_at is not null) then 0 else 1 end")
                ->orderByDesc('created_at'))
            ->columns([
                TextColumn::make('needs')
                    ->label('')
                    ->badge()
                    ->state(fn (ReturnRequest $record) => $record->needsAdmin() ? 'Needs Sokoni' : null)
                    ->color('danger'),
                TextColumn::make('order.code')->label('Order')->searchable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'refunded' => 'success',
                        'rejected', 'disputed' => 'danger',
                        'closed' => 'gray',
                        default => 'warning',
                    }),
                TextColumn::make('reason')->formatStateUsing(fn (string $state) => __("returns.reasons.{$state}", [], 'en')),
                TextColumn::make('buyer.name')->label('Buyer')->searchable(),
                TextColumn::make('shop.shop_name')->label('Shop')->searchable(),
                TextColumn::make('payer')->label('Ride paid by')->badge(),
                TextColumn::make('fee')->label('Ride fee')->money('TZS', divideBy: 1)->placeholder('—'),
                TextColumn::make('fee_status')->label('Fee')->placeholder('—'),
                TextColumn::make('escalation_reason')->label('Escalated')->placeholder('—'),
                TextColumn::make('created_at')->label('Requested')->dateTime()->sortable(),
            ])
            ->filters([
                Filter::make('needs_admin')
                    ->label('Needs Sokoni (escalated or disputed)')
                    ->toggle()
                    ->query(fn (Builder $query) => ReturnRequestResource::needsAdmin($query)),
                SelectFilter::make('status')->options(array_combine(ReturnRequest::STATUSES, ReturnRequest::STATUSES)),
                SelectFilter::make('payer')->options(['buyer' => 'Buyer', 'seller' => 'Seller (owed by seller)']),
            ])
            ->recordActions([ViewAction::make()]);
    }
}
