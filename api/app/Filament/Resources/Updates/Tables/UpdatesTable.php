<?php

namespace App\Filament\Resources\Updates\Tables;

use App\Models\Update;
use App\Support\ActivityLogger;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class UpdatesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['seller', 'product']))
            ->columns([
                TextColumn::make('seller.shop_name')
                    ->label('Shop')
                    ->searchable(),
                TextColumn::make('type')
                    ->badge(),
                TextColumn::make('caption')
                    ->limit(50)
                    ->placeholder('—')
                    ->wrap(),
                TextColumn::make('product.title')
                    ->label('Linked product')
                    ->placeholder('—'),
                TextColumn::make('status')
                    ->badge()
                    ->state(fn (Update $record) => $record->isExpired() ? 'Expired' : 'Active')
                    ->color(fn (string $state) => $state === 'Active' ? 'success' : 'gray'),
                TextColumn::make('expires_at')
                    ->label('Expires')
                    ->dateTime(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(['active' => 'Active', 'expired' => 'Expired'])
                    ->query(function (Builder $query, array $data): Builder {
                        return match ($data['value'] ?? null) {
                            'active' => $query->where('expires_at', '>', now()),
                            'expired' => $query->where('expires_at', '<=', now()),
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
                    ->action(function (Update $record) {
                        ActivityLogger::record(Auth::user(), 'update.removed', $record);
                        $record->delete();
                        Notification::make()->title('Update removed')->success()->send();
                    }),
            ])
            ->toolbarActions([])
            ->defaultPaginationPageOption(25);
    }
}
