<?php

namespace App\Filament\Resources\Categories\Schemas;

use App\Models\Category;
use Filament\Actions\Action;
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
                    // Part 2 (client feedback): "no way to remove a
                    // category image... add a preview of the current
                    // image [and] a remove action." Both already default
                    // to true on FileUpload, but made explicit here since
                    // that default was clearly not obvious/discoverable
                    // enough on its own — paired with a bigger preview
                    // and the hint action below, which removal is now.
                    ->previewable()
                    ->imagePreviewHeight('160')
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
                    ->dehydrateStateUsing(fn (?string $state) => $state ? Storage::disk('public')->url($state) : null)
                    // Part 2 (client feedback): FileUpload's own default
                    // "x" thumbnail button only clears the FORM's live
                    // state — an admin still has to remember to hit the
                    // page's general Save afterwards for it to actually
                    // take effect, and until then the field shows empty
                    // rather than "actually removed." This hint action
                    // clears the stored file and the DB column
                    // immediately, so "removed, falls back to the icon
                    // tile" is true the moment it's clicked, not after a
                    // second, separate step.
                    ->hintAction(
                        Action::make('removeCategoryImage')
                            ->label('Remove photo')
                            ->icon('heroicon-o-trash')
                            ->color('danger')
                            ->visible(fn (?string $state, ?Category $record): bool => filled($state) || filled($record?->image))
                            ->requiresConfirmation()
                            ->action(function (FileUpload $component, ?Category $record) {
                                if ($record?->image) {
                                    $relative = str($record->image)->after(Storage::disk('public')->url(''));
                                    Storage::disk('public')->delete($relative);
                                    $record->update(['image' => null]);
                                }
                                $component->state(null);
                            })
                    ),
                TextInput::make('sort_order')
                    ->required()
                    ->numeric()
                    ->default(0),
                Toggle::make('is_active')
                    ->required(),
            ]);
    }
}
