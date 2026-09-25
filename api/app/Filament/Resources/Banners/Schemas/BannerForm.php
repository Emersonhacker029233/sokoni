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
        return $schema->components([
            TextInput::make('title')
                ->required(),
            static::imageField(),
            ...static::sharedFields(),
        ]);
    }

    /**
     * Part 4 (client feedback): "when creating banners for a position,
     * allow selecting several images in one go and creating a banner
     * record for each, rather than repeating the form." Used only by
     * CreateBanner's own overridden form() — everything except the image
     * field is identical to (and shared with) the single-banner
     * configure() above, since every one of those other values is meant
     * to apply to every banner this bulk create produces (see
     * CreateBanner::handleRecordCreation()), individually editable
     * afterwards from the list like any other banner.
     */
    public static function configureForBulkCreate(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('title')
                ->required()
                ->helperText('Applied to every banner this creates — rename any of them individually afterwards.'),
            FileUpload::make('image_paths')
                ->label('Images')
                ->image()
                ->disk('public')
                ->directory('banners')
                ->multiple()
                ->reorderable()
                ->required()
                ->automaticallyResizeImagesMode('contain')
                ->automaticallyResizeImagesToWidth('1920')
                ->automaticallyUpscaleImagesWhenResizing(false)
                ->maxSize(2048)
                ->previewable()
                ->imagePreviewHeight('120')
                ->helperText(
                    'Select several images to create one banner per image in this position — each gets '
                    .'this same title, link, schedule and active state to start with, and its own sort '
                    .'order (in the order shown here), all editable individually afterwards. '
                    .static::sizeGuidance()
                ),
            ...static::sharedFields(),
        ]);
    }

    private static function imageField(): FileUpload
    {
        return FileUpload::make('image_path')
            ->label('Image')
            ->image()
            ->disk('public')
            ->directory('banners')
            ->required()
            // Part B (client feedback): "compression on upload — this is
            // a 3G market." Resizes (and re-encodes, which is where the
            // real byte savings come from) in the browser before the
            // file ever leaves it, rather than only shrinking it after a
            // slow upload has already happened — the more meaningful
            // saving on this connection quality. 1920px covers every
            // position's guidance below at full resolution on a large
            // desktop display; maxSize is a hard backstop against
            // whatever the browser's own resize can't help with (an
            // unusually dense source image, an animated GIF).
            ->automaticallyResizeImagesMode('contain')
            ->automaticallyResizeImagesToWidth('1920')
            ->automaticallyUpscaleImagesWhenResizing(false)
            ->maxSize(2048)
            // Part 2 (client feedback): "the same applies anywhere else
            // an image can be uploaded but not removed — check
            // banners..." — no *remove* action here on purpose: this
            // field is `->required()`, a banner with no image isn't a
            // valid banner (unlike a Category, which has a real
            // icon-tile fallback to remove down to). "Replace" already
            // works as-is — dropping a new file over an existing one in
            // this same slot swaps it, no separate action needed.
            // Explicit preview, same reasoning as CategoryForm.
            ->previewable()
            ->imagePreviewHeight('160')
            ->helperText(static::sizeGuidance())
            // The website reads image_path as a full public URL — same
            // convention as product photos and seller logos — not the
            // disk-relative path FileUpload stores/expects by default.
            // dehydrateStateUsing converts to a full URL on save;
            // afterStateHydrated does the reverse when editing an
            // existing banner, stripping the disk's base URL back off so
            // FileUpload recognizes the existing image instead of
            // showing the field as empty and (since it's required)
            // failing validation on any edit that doesn't also re-upload
            // a new file.
            ->afterStateHydrated(function (FileUpload $component, ?string $state) {
                if (! $state) {
                    return;
                }
                $base = Storage::disk('public')->url('');
                $component->state(str_starts_with($state, $base) ? substr($state, strlen($base)) : $state);
            })
            ->dehydrateStateUsing(fn (?string $state) => $state ? Storage::disk('public')->url($state) : null);
    }

    private static function sizeGuidance(): string
    {
        return 'Recommended sizes — Home hero/mid, Category top: 1600×500px wide banner. '
            .'Sidebar: 300×600px. Search background: 1600×400px, keep the important '
            .'artwork within the outer thirds — the centre third is dimmed for the '
            .'search field. Category strip side: 200×200px square. Near you side: '
            .'300×250px. In Focus poster: 600×800px portrait (3:4). Max 2MB — larger '
            .'images are resized automatically.';
    }

    /** @return array<int, \Filament\Schemas\Components\Component> */
    private static function sharedFields(): array
    {
        return [
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
        ];
    }
}
