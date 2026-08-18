<?php

namespace App\Filament\Resources\Reviews\Tables;

use App\Models\Review;
use App\Support\ActivityLogger;
use Filament\Actions\Action;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class ReviewsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['seller', 'buyer', 'order.items']))
            ->columns([
                TextColumn::make('rating')
                    ->badge()
                    ->color(fn (int $state) => $state >= 4 ? 'success' : ($state <= 2 ? 'danger' : 'warning'))
                    ->formatStateUsing(fn (int $state) => str_repeat('★', $state)),
                TextColumn::make('product_summary')
                    ->label('Product(s)')
                    ->state(fn (Review $record) => \App\Filament\Resources\Reviews\ReviewProductSummary::for($record)),
                TextColumn::make('buyer.name')
                    ->label('Buyer')
                    ->searchable(),
                TextColumn::make('seller.shop_name')
                    ->label('Seller')
                    ->searchable(),
                TextColumn::make('comment')
                    ->limit(50)
                    ->placeholder('—')
                    ->wrap(),
                IconColumn::make('reply')
                    ->label('Replied')
                    ->boolean()
                    ->state(fn (Review $record) => $record->hasReply()),
                IconColumn::make('is_hidden')
                    ->label('Hidden')
                    ->boolean(),
                TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('rating')
                    ->options([1 => '1', 2 => '2', 3 => '3', 4 => '4', 5 => '5']),
                TernaryFilter::make('is_hidden'),
            ])
            ->recordActions([
                Action::make('hide')
                    ->label('Hide')
                    ->icon('heroicon-o-eye-slash')
                    ->color('danger')
                    ->visible(fn (Review $record) => ! $record->is_hidden)
                    ->requiresConfirmation()
                    ->action(function (Review $record) {
                        $record->forceFill(['is_hidden' => true])->save();
                        ActivityLogger::record(Auth::user(), 'review.hidden', $record);
                        Notification::make()->title('Review hidden')->success()->send();
                    }),
                Action::make('unhide')
                    ->label('Unhide')
                    ->icon('heroicon-o-eye')
                    ->color('success')
                    ->visible(fn (Review $record) => $record->is_hidden)
                    ->action(function (Review $record) {
                        $record->forceFill(['is_hidden' => false])->save();
                        ActivityLogger::record(Auth::user(), 'review.unhidden', $record);
                        Notification::make()->title('Review unhidden')->success()->send();
                    }),
            ])
            ->toolbarActions([])
            ->defaultPaginationPageOption(25);
    }
}
