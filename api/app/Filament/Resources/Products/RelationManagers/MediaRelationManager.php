<?php

namespace App\Filament\Resources\Products\RelationManagers;

use App\Models\Product;
use App\Models\ProductMedia;
use App\Support\ActivityLogger;
use App\Support\Settings;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * D1 (tester feedback): "media with individual image removal." A product
 * must already exist before photos can attach to it (same reasoning
 * ShopProductMediaController's own docblock gives), so this only appears
 * on EditProduct, never CreateProduct — Filament relation managers already
 * behave this way by default (they need a persisted parent record).
 * Images only, matching this admin surface's own moderation-first scope;
 * video re-encoding/thumbnailing isn't something an admin form should be
 * doing (the seller/app upload paths already own that).
 */
class MediaRelationManager extends RelationManager
{
    protected static string $relationship = 'media';

    protected static ?string $title = 'Photos';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            // Stored to a scratch directory first, then re-read and
            // deleted once ImageVariants::generate() has produced the real
            // thumb/card/full sizes from it — FileUpload's own storage
            // only ever gives back a stored disk path, not the raw
            // UploadedFile ImageVariants needs, and this admin path still
            // needs the exact same three sizes the seller/app upload
            // paths produce, not a single arbitrary-size copy.
            FileUpload::make('file')
                ->label('Photo')
                ->image()
                ->disk('public')
                ->directory('products-tmp')
                ->required(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('type')
            ->columns([
                ImageColumn::make('thumb_path')
                    ->label('')
                    ->square()
                    ->size(80),
                TextColumn::make('type')
                    ->badge(),
                TextColumn::make('sort'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Add photo')
                    ->using(function (array $data): ProductMedia {
                        /** @var Product $product */
                        $product = $this->getOwnerRecord();

                        $maxItems = Settings::maxMediaPerProduct();
                        if ($product->media()->count() >= $maxItems) {
                            throw ValidationException::withMessages(['file' => "A product can have at most {$maxItems} photos."]);
                        }

                        $tmpPath = Storage::disk('public')->path($data['file']);
                        $uploaded = new UploadedFile($tmpPath, basename($tmpPath), test: true);

                        $media = ProductMedia::createFromUpload($product, $uploaded);
                        Storage::disk('public')->delete($data['file']);
                        ActivityLogger::record(Auth::user(), 'media.added', $product);

                        return $media;
                    }),
            ])
            ->recordActions([
                Action::make('delete')
                    ->label('Remove')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function (ProductMedia $record) {
                        $product = $record->product;
                        $record->deleteWithFiles();
                        ActivityLogger::record(Auth::user(), 'media.deleted', $product);
                        Notification::make()->title('Photo removed')->success()->send();
                    }),
            ]);
    }
}
