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
