<?php

namespace App\Filament\Resources\ProductMedia\Tables;

use App\Models\ProductMedia;
use App\Support\ActivityLogger;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class ProductMediaTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->with('product.seller'))
            ->columns([
                ImageColumn::make('thumb_path')
                    ->label('')
                    ->square()
                    ->size(48),
                TextColumn::make('type')
                    ->badge(),
                TextColumn::make('product.title')
                    ->label('Product')
                    ->searchable()
                    ->url(fn (ProductMedia $record) => $record->product_id
                        ? route('filament.admin.resources.products.view', $record->product_id)
                        : null),
                TextColumn::make('product.seller.shop_name')
                    ->label('Shop')
                    ->searchable(),
                TextColumn::make('duration')
                    ->suffix('s')
                    ->placeholder('—'),
                TextColumn::make('created_at')
                    ->label('Uploaded')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->options(['image' => 'Image', 'video' => 'Video']),
            ])
            ->recordActions([
                Action::make('delete')
                    ->label('Delete')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function (ProductMedia $record) {
                        $product = $record->product;
                        ActivityLogger::record(Auth::user(), 'media.deleted', $product, "Deleted a {$record->type} from \"{$product?->title}\"");
                        // D1 (tester feedback): this used to be a bare
                        // $record->delete(), leaking the physical file(s)
                        // on the public disk forever — see
                        // ProductMedia::deleteWithFiles()'s own docblock.
                        $record->deleteWithFiles();
                        Notification::make()->title('Media deleted')->success()->send();
                    }),
            ])
            ->toolbarActions([])
            ->defaultPaginationPageOption(25);
    }
}
