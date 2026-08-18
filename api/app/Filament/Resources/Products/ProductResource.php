<?php

namespace App\Filament\Resources\Products;

use App\Filament\Resources\Products\Pages\ListProducts;
use App\Filament\Resources\Products\Pages\ViewProduct;
use App\Filament\Resources\Products\Schemas\ProductInfolist;
use App\Filament\Resources\Products\Tables\ProductsTable;
use App\Models\Product;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * Catalog browsing/moderation (CLAUDE.md admin rebuild, Section 4). No
 * create/edit pages — products are only ever created/edited by sellers
 * through the app, same reasoning as SellerProfileResource/ReportResource:
 * this is a review-and-moderate surface (hide/unhide/feature, bulk hide,
 * bulk category reassignment — see ProductsTable), not a data-entry form.
 */
class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingBag;

    protected static \UnitEnum|string|null $navigationGroup = 'Catalog';

    // See CategoryResource for why this matters.
    protected static ?string $recordTitleAttribute = 'title';

    /** "Global search across products, shops, users and orders" (CLAUDE.md admin rebuild, Section 6). */
    public static function getGloballySearchableAttributes(): array
    {
        return ['title', 'seller.shop_name'];
    }

    public static function infolist(Schema $schema): Schema
    {
        return ProductInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProductsTable::configure($table);
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
            'index' => ListProducts::route('/'),
            'view' => ViewProduct::route('/{record}'),
        ];
    }
}
