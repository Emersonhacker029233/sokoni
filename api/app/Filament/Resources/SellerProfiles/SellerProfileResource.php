<?php

namespace App\Filament\Resources\SellerProfiles;

use App\Filament\Resources\SellerProfiles\Pages\ListSellerProfiles;
use App\Filament\Resources\SellerProfiles\Pages\ViewSellerProfile;
use App\Filament\Resources\SellerProfiles\Schemas\SellerProfileInfolist;
use App\Filament\Resources\SellerProfiles\Tables\SellerProfilesTable;
use App\Models\SellerProfile;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * The seller verification queue (CLAUDE.md feature 9). No create/edit
 * pages: sellers create and edit their own profile through the app's
 * onboarding wizard — this resource is purely the admin's review-and-
 * decide surface (list + a NIDA/licence/map view, verify/reject actions
 * on the table itself).
 */
class SellerProfileResource extends Resource
{
    protected static ?string $model = SellerProfile::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static \UnitEnum|string|null $navigationGroup = 'Trust & Safety';

    public static function infolist(Schema $schema): Schema
    {
        return SellerProfileInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SellerProfilesTable::configure($table);
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
            'index' => ListSellerProfiles::route('/'),
            'view' => ViewSellerProfile::route('/{record}'),
        ];
    }
}
