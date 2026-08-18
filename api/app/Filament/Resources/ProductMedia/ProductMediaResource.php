<?php

namespace App\Filament\Resources\ProductMedia;

use App\Filament\Resources\ProductMedia\Pages\ListProductMedia;
use App\Filament\Resources\ProductMedia\Tables\ProductMediaTable;
use App\Models\ProductMedia;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/** Every uploaded product photo/video, browsable across the whole catalog — moderation/cleanup surface, no create/edit. */
class ProductMediaResource extends Resource
{
    protected static ?string $model = ProductMedia::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPhoto;

    protected static \UnitEnum|string|null $navigationGroup = 'Catalog';

    protected static ?string $navigationLabel = 'Media';

    public static function table(Table $table): Table
    {
        return ProductMediaTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProductMedia::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
