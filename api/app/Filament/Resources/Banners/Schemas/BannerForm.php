<?php

namespace App\Filament\Resources\Banners\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Storage;

class BannerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')
                    ->required(),
                FileUpload::make('image_path')
                    ->label('Image')
                    ->image()
                    ->disk('public')
                    ->directory('banners')
                    ->required()
                    // Part B (client feedback): "compression on upload —
                    // this is a 3G market." Resizes (and re-encodes, which
                    // is where the real byte savings come from) in the
                    // browser before the file ever leaves it, rather than
                    // only shrinking it after a slow upload has already
                    // happened — the more meaningful saving on this
                    // connection quality. 1920px covers every position's
                    // guidance below at full resolution on a large
                    // desktop display; maxSize is a hard backstop against
                    // whatever the browser's own resize can't help with
                    // (an unusually dense source image, an animated GIF).
                    ->automaticallyResizeImagesMode('contain')
                    ->automaticallyResizeImagesToWidth('1920')
                    ->automaticallyUpscaleImagesWhenResizing(false)
                    ->maxSize(2048)
                    ->helperText(
                        'Recommended sizes — Home hero/mid, Category top: 1600×500px wide banner. '
                        .'Sidebar: 300×600px. Search background: 1600×400px, keep the important '
                        .'artwork within the outer thirds — the centre third is dimmed for the '
                        .'search field. Category strip side: 200×200px square. Near you side: '
                        .'300×250px. In Focus poster: 600×800px portrait (3:4). Max 2MB — larger '
                        .'images are resized automatically.'
                    )
                    // The website reads image_path as a full public URL —
                    // same convention as product photos and seller logos —
                    // not the disk-relative path FileUpload stores/expects
                    // by default. dehydrateStateUsing converts to a full
                    // URL on save; afterStateHydrated does the reverse when
                    // editing an existing banner, stripping the disk's
                    // base URL back off so FileUpload recognizes the
                    // existing image instead of showing the field as empty
                    // and (since it's required) failing validation on any
                    // edit that doesn't also re-upload a new file.
                    ->afterStateHydrated(function (FileUpload $component, ?string $state) {
                        if (! $state) {
                            return;
                        }
                        $base = Storage::disk('public')->url('');
                        $component->state(str_starts_with($state, $base) ? substr($state, strlen($base)) : $state);
                    })
                    ->dehydrateStateUsing(fn (?string $state) => $state ? Storage::disk('public')->url($state) : null),
                TextInput::make('link_url')
                    ->label('Link URL')
                    ->url()
                    ->required(),
                Select::make('position')
                    ->options([
                        'home_hero' => 'Home — hero (below search)',
                        'home_mid' => 'Home — mid-page',
                        'category_top' => 'Category page — top',
                        'sidebar' => 'Sidebar (category/search)',
                        // Part B (client feedback): noon.com-pattern ad inventory.
                        'search_background' => 'Home — search bar background',
                        'category_strip_side' => 'Home — beside the category strip',
                        'near_you_side' => 'Home — beside "Near you"',
                        // Part 4 (client feedback): "In Focus" advertising band.
                        'in_focus' => 'Home — In Focus poster',
                    ])
                    ->required(),
                TextInput::make('sort_order')
                    ->numeric()
                    ->default(0)
                    ->required()
                    ->helperText('Lower numbers show first when a position ever holds more than one banner.'),
                DateTimePicker::make('starts_at')
                    ->label('Starts')
                    ->helperText('Leave blank to start immediately.'),
                DateTimePicker::make('ends_at')
                    ->label('Ends')
                    ->helperText('Leave blank to run indefinitely while active.'),
                Toggle::make('is_active')
                    ->default(true)
                    ->required(),
            ]);
    }
}
