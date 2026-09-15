<?php

namespace App\Filament\Resources\Categories\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Storage;

class CategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('parent_id')
                    ->label('Parent category')
                    ->relationship('parent', 'name_en')
                    ->searchable(),
                TextInput::make('name_en')
                    ->required(),
                TextInput::make('name_sw')
                    ->required(),
                TextInput::make('icon')
                    ->helperText('Used when no photo is set below — falls back to the existing icon set on a brand-yellow tile.'),
                // Part 3 (client feedback): noon.com-style category tiles
                // on the homepage. Optional — a category with no image
                // falls back to its icon (above) on a yellow tile, so
                // this never leaves a blank/broken tile for a category
                // the client hasn't photographed yet.
                FileUpload::make('image')
                    ->label('Photo')
                    ->image()
                    ->disk('public')
                    ->directory('categories')
                    // Same compression approach as Banners (Part B) —
                    // resize/re-encode in the browser before upload, the
                    // saving that actually matters on 3G, not just after
                    // a slow upload has already happened. Tiles render
                    // small and square/circular, so a much smaller cap
                    // than a full-width banner is enough here.
                    ->automaticallyResizeImagesMode('cover')
                    ->automaticallyResizeImagesToWidth('600')
                    ->automaticallyUpscaleImagesWhenResizing(false)
                    ->maxSize(1024)
                    ->helperText('Square works best — the tile crops to a circle/rounded square. Recommended 600×600px. Max 1MB — larger images are resized automatically. Leave empty to use the icon above instead.')
                    // Same full-URL convention as Banner.image_path and
                    // SellerProfile.logo — see BannerForm's own docblock
                    // on why both afterStateHydrated and
                    // dehydrateStateUsing are needed together.
                    ->afterStateHydrated(function (FileUpload $component, ?string $state) {
                        if (! $state) {
                            return;
                        }
                        $base = Storage::disk('public')->url('');
                        $component->state(str_starts_with($state, $base) ? substr($state, strlen($base)) : $state);
                    })
                    ->dehydrateStateUsing(fn (?string $state) => $state ? Storage::disk('public')->url($state) : null),
                TextInput::make('sort_order')
                    ->required()
                    ->numeric()
                    ->default(0),
                Toggle::make('is_active')
                    ->required(),
            ]);
    }
}
