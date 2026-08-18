<?php

namespace App\Filament\Resources\Showcases;

use App\Filament\Resources\Showcases\Pages\ListShowcases;
use App\Filament\Resources\Showcases\Tables\ShowcasesTable;
use App\Models\Showcase;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/** List/moderate only — Showcases are vertical product videos posted from the app (CLAUDE.md Part 3), never created here. */
class ShowcaseResource extends Resource
{
    protected static ?string $model = Showcase::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedVideoCamera;

    protected static \UnitEnum|string|null $navigationGroup = 'Community';

    public static function table(Table $table): Table
    {
        return ShowcasesTable::configure($table);
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
            'index' => ListShowcases::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
