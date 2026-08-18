<?php

namespace App\Filament\Resources\Showcases\Tables;

use App\Models\Showcase;
use App\Support\ActivityLogger;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class ShowcasesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['seller', 'product']))
            ->columns([
                ImageColumn::make('thumb_path')
                    ->label('')
                    ->square()
                    ->size(48),
                TextColumn::make('seller.shop_name')
                    ->label('Shop')
                    ->searchable(),
                TextColumn::make('product.title')
                    ->label('Product')
                    ->searchable(),
                TextColumn::make('caption')
                    ->limit(50)
                    ->placeholder('—'),
                TextColumn::make('views')
                    ->sortable(),
                TextColumn::make('duration')
                    ->suffix('s'),
                TextColumn::make('created_at')
                    ->label('Posted')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([])
            ->recordActions([
                Action::make('remove')
                    ->label('Remove')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function (Showcase $record) {
                        ActivityLogger::record(Auth::user(), 'showcase.removed', $record, "On \"{$record->product?->title}\"");
                        $record->delete();
                        Notification::make()->title('Showcase removed')->success()->send();
                    }),
            ])
            ->toolbarActions([])
            ->defaultPaginationPageOption(25);
    }
}
