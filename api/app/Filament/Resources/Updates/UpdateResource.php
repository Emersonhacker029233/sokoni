<?php

namespace App\Filament\Resources\Updates;

use App\Filament\Resources\Updates\Pages\ListUpdates;
use App\Filament\Resources\Updates\Tables\UpdatesTable;
use App\Models\Update;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/** List/moderate only — Updates are 24h shop notices posted from the app (CLAUDE.md Part 3), auto-expired by a scheduled command, never created here. */
class UpdateResource extends Resource
{
    protected static ?string $model = Update::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMegaphone;

    protected static \UnitEnum|string|null $navigationGroup = 'Community';

    public static function table(Table $table): Table
    {
        return UpdatesTable::configure($table);
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
            'index' => ListUpdates::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
