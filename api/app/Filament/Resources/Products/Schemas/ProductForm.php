<?php

namespace App\Filament\Resources\Products\Schemas;

use App\Models\Category;
use App\Models\Product;
use App\Models\SellerProfile;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

/**
 * D1 (tester feedback): "full CRUD for products... create, edit every
 * field." This resource previously had no form at all — ProductResource's
 * own docblock argued products should only ever be created/edited by
 * sellers through the app, but the client explicitly asked for admin
 * create/edit here, which supersedes that earlier call. `category_id` is
 * the one real column (see Product::scopeInCategory's own note — a
 * "subcategory" is just a Category row with parent_id set, not a separate
 * concept), split into two selects the same way the website's own
 * `shop-product-form.blade.php` does: `category_parent_id` is a virtual,
 * never-persisted field purely to filter the second select's options.
 * Media is handled by MediaRelationManager, not here — a product must
 * already exist before photos can attach to it (same reasoning
 * ShopProductMediaController's own docblock gives), so it only appears on
 * Edit, never Create.
 */
class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('seller_id')
                    ->label('Seller')
                    ->options(fn () => SellerProfile::query()->pluck('shop_name', 'id'))
                    ->searchable()
                    ->required(),
                TextInput::make('title')
                    ->required()
                    ->maxLength(255),
                Textarea::make('description')
                    ->rows(4)
                    ->maxLength(5000),
                Select::make('category_parent_id')
                    ->label('Category')
                    ->options(fn () => Category::whereNull('parent_id')->where('is_active', true)->orderBy('sort_order')->pluck('name_en', 'id'))
                    ->live()
                    ->dehydrated(false)
                    ->required()
                    ->afterStateHydrated(function (Select $component, ?Product $record) {
                        if ($record?->category) {
                            $component->state($record->category->parent_id ?? $record->category->id);
                        }
                    }),
                Select::make('category_id')
                    ->label('Subcategory')
                    ->options(function (Get $get) {
                        $parentId = $get('category_parent_id');
                        if (! $parentId) {
                            return [];
                        }
                        $parent = Category::find($parentId);
                        $options = $parent ? [$parent->id => 'Use parent category ('.$parent->name_en.')'] : [];
                        foreach (Category::where('parent_id', $parentId)->where('is_active', true)->orderBy('sort_order')->get() as $child) {
                            $options[$child->id] = $child->name_en;
                        }

                        return $options;
                    })
                    ->required(),
                Select::make('condition')
                    ->options(['new' => 'New', 'used' => 'Used'])
                    ->required(),
                TextInput::make('price')
                    ->label('Price (TZS)')
                    ->numeric()
                    ->minValue(0)
                    ->required(),
                TextInput::make('stock')
                    ->numeric()
                    ->minValue(0)
                    ->required(),
                Toggle::make('is_active')
                    ->label('Active')
                    ->default(true)
                    ->helperText('Off keeps the listing in the seller\'s shop but out of the public feed — same as the seller\'s own pause/unpause toggle. Use the table\'s Hide action instead for moderation, which is a separate flag.')
                    ->required(),
            ]);
    }
}
