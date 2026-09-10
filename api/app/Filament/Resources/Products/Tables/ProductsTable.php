<?php

namespace App\Filament\Resources\Products\Tables;

use App\Models\Category;
use App\Models\Product;
use App\Models\SellerProfile;
use App\Support\ActivityLogger;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;

class ProductsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['seller', 'category', 'media']))
            ->columns([
                ImageColumn::make('thumb')
                    ->label('')
                    ->state(fn (Product $record) => $record->media->first()?->thumb_path)
                    ->square()
                    ->size(48),
                TextColumn::make('title')
                    ->searchable()
                    ->limit(40)
                    ->sortable(),
                TextColumn::make('seller.shop_name')
                    ->label('Shop')
                    ->searchable()
                    ->url(fn (Product $record) => $record->seller_id
                        ? route('filament.admin.resources.seller-profiles.view', $record->seller_id)
                        : null),
                TextColumn::make('category.name_en')
                    ->label('Category')
                    ->placeholder('—'),
                TextColumn::make('price')
                    ->money('TZS', divideBy: 1)
                    ->sortable(),
                TextColumn::make('stock')
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->state(fn (Product $record) => match (true) {
                        $record->is_hidden => 'Hidden',
                        ! $record->is_active => 'Inactive',
                        default => 'Live',
                    })
                    ->color(fn (string $state) => match ($state) {
                        'Live' => 'success',
                        'Hidden' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('is_sponsored')
                    ->label('Sponsored')
                    ->badge()
                    ->state(fn (Product $record) => $record->is_sponsored && $record->sponsored_until?->isFuture() ? 'Sponsored' : null)
                    ->color('warning')
                    ->placeholder('—'),
                TextColumn::make('views')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('category_id')
                    ->label('Category')
                    ->options(fn () => Category::query()->pluck('name_en', 'id')),
                SelectFilter::make('seller_id')
                    ->label('Seller')
                    ->searchable()
                    ->options(fn () => SellerProfile::query()->pluck('shop_name', 'id')),
                SelectFilter::make('status')
                    ->options([
                        'live' => 'Live',
                        'hidden' => 'Hidden',
                        'inactive' => 'Inactive',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return match ($data['value'] ?? null) {
                            'live' => $query->where('is_active', true)->where('is_hidden', false),
                            'hidden' => $query->where('is_hidden', true),
                            'inactive' => $query->where('is_active', false),
                            default => $query,
                        };
                    }),
                TernaryFilter::make('is_sponsored')
                    ->label('Sponsored'),
                TrashedFilter::make(),
                Filter::make('price')
                    ->schema([
                        \Filament\Forms\Components\TextInput::make('min')->numeric()->label('Min price (TZS)'),
                        \Filament\Forms\Components\TextInput::make('max')->numeric()->label('Max price (TZS)'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['min'] ?? null, fn (Builder $q, $min) => $q->where('price', '>=', $min))
                            ->when($data['max'] ?? null, fn (Builder $q, $max) => $q->where('price', '<=', $max));
                    }),
            ])
            ->recordActions([
                ViewAction::make(),
                Action::make('hide')
                    ->label('Hide')
                    ->icon('heroicon-o-eye-slash')
                    ->color('danger')
                    ->visible(fn (Product $record) => ! $record->is_hidden)
                    ->requiresConfirmation()
                    ->action(function (Product $record) {
                        $record->forceFill(['is_hidden' => true])->save();
                        ActivityLogger::record(Auth::user(), 'product.hidden', $record);
                        Notification::make()->title('Product hidden')->success()->send();
                    }),
                Action::make('unhide')
                    ->label('Unhide')
                    ->icon('heroicon-o-eye')
                    ->color('success')
                    ->visible(fn (Product $record) => $record->is_hidden)
                    ->action(function (Product $record) {
                        $record->forceFill(['is_hidden' => false])->save();
                        ActivityLogger::record(Auth::user(), 'product.unhidden', $record);
                        Notification::make()->title('Product unhidden')->success()->send();
                    }),
                Action::make('feature')
                    ->label(fn (Product $record) => $record->is_sponsored && $record->sponsored_until?->isFuture() ? 'Unfeature' : 'Feature')
                    ->icon('heroicon-o-star')
                    ->color('warning')
                    ->action(function (Product $record) {
                        $nowSponsored = $record->is_sponsored && $record->sponsored_until?->isFuture();
                        $record->forceFill([
                            'is_sponsored' => ! $nowSponsored,
                            'sponsored_until' => $nowSponsored ? null : now()->addDays(7),
                        ])->save();
                        ActivityLogger::record(Auth::user(), $nowSponsored ? 'product.unfeatured' : 'product.featured', $record);
                        Notification::make()->title($nowSponsored ? 'Product unfeatured' : 'Product featured for 7 days')->success()->send();
                    }),
                // C2 (tester feedback): "delete, not just hide" — soft
                // delete (recoverable, see Product::class's own SoftDeletes
                // note), naming exactly what's removed and requiring a
                // reason before it fires, logged like every other
                // moderation action on this table.
                Action::make('delete')
                    ->label('Delete')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading(fn (Product $record) => "Delete \"{$record->title}\"?")
                    ->modalDescription(fn (Product $record) => "This removes \"{$record->title}\" from {$record->seller->shop_name}'s shop and the public feed immediately. It stays recoverable (Trashed filter) until its photos/videos are permanently purged after 30 days.")
                    ->schema([Textarea::make('reason')->label('Reason')->required()])
                    ->action(function (Product $record, array $data) {
                        $record->delete();
                        ActivityLogger::record(Auth::user(), 'product.deleted', $record, $data['reason']);
                        Notification::make()->title('Product deleted')->success()->send();
                    }),
                RestoreAction::make()
                    ->action(function (Product $record) {
                        $record->restore();
                        ActivityLogger::record(Auth::user(), 'product.restored', $record);
                        Notification::make()->title('Product restored')->success()->send();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('bulk_hide')
                        ->label('Hide selected')
                        ->icon('heroicon-o-eye-slash')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->action(function (Collection $records) {
                            foreach ($records as $record) {
                                $record->forceFill(['is_hidden' => true])->save();
                                ActivityLogger::record(Auth::user(), 'product.hidden', $record, 'Bulk action');
                            }
                            Notification::make()->title($records->count().' products hidden')->success()->send();
                        }),
                    BulkAction::make('bulk_delete')
                        ->label('Delete selected')
                        ->icon('heroicon-o-trash')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalDescription(fn (Collection $records) => 'This removes '.$records->count().' product(s) from their shops and the public feed immediately. They stay recoverable (Trashed filter) until permanently purged after 30 days.')
                        ->schema([Textarea::make('reason')->label('Reason')->required()])
                        ->action(function (Collection $records, array $data) {
                            foreach ($records as $record) {
                                $record->delete();
                                ActivityLogger::record(Auth::user(), 'product.deleted', $record, $data['reason'], ['bulk' => true]);
                            }
                            Notification::make()->title($records->count().' products deleted')->success()->send();
                        }),
                    BulkAction::make('bulk_reassign_category')
                        ->label('Reassign category')
                        ->icon('heroicon-o-arrow-path')
                        ->schema([
                            Select::make('category_id')
                                ->label('New category')
                                ->options(fn () => Category::query()->pluck('name_en', 'id'))
                                ->required(),
                        ])
                        ->action(function (Collection $records, array $data) {
                            foreach ($records as $record) {
                                $record->forceFill(['category_id' => $data['category_id']])->save();
                                ActivityLogger::record(Auth::user(), 'product.category_reassigned', $record, null, ['category_id' => $data['category_id']]);
                            }
                            Notification::make()->title($records->count().' products reassigned')->success()->send();
                        }),
                ]),
            ])
            ->defaultPaginationPageOption(25);
    }
}
