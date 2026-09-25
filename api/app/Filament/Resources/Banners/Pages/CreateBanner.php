<?php

namespace App\Filament\Resources\Banners\Pages;

use App\Filament\Resources\Banners\BannerResource;
use App\Filament\Resources\Banners\Schemas\BannerForm;
use App\Models\Banner;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;

/**
 * Part 4 (client feedback): "when creating banners for a position,
 * allow selecting several images in one go and creating a banner
 * record for each, rather than repeating the form." Overrides the
 * resource's own shared single-image form (used everywhere else,
 * including EditBanner) with a multi-image one just for this page —
 * every other field (title, link, position, schedule, active state)
 * is shared across all the banners this one submission creates, each
 * individually editable afterwards like any other banner.
 */
class CreateBanner extends CreateRecord
{
    protected static string $resource = BannerResource::class;

    private int $createdCount = 1;

    public function form(Schema $schema): Schema
    {
        return BannerForm::configureForBulkCreate($schema);
    }

    protected function handleRecordCreation(array $data): Model
    {
        $imagePaths = Arr::wrap($data['image_paths'] ?? []);
        unset($data['image_paths']);

        $records = collect($imagePaths)->values()->map(
            fn (string $path, int $index) => Banner::create([
                ...$data,
                'image_path' => Storage::disk('public')->url($path),
                // Distinct, deterministic sort order per banner (in the
                // order they were added here) rather than every banner
                // this creates sharing the exact same value — scopeLive()
                // orders by this column, so identical values across a
                // batch would leave their relative order undefined.
                'sort_order' => (int) ($data['sort_order'] ?? 0) + $index,
            ])
        );

        $this->createdCount = $records->count();

        return $records->last();
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return $this->createdCount === 1
            ? 'Banner created'
            : "{$this->createdCount} banners created";
    }

    /**
     * There's no longer a single canonical "the record just created" to
     * open an edit page for once this can create several at once — the
     * list, where every one of them (and its own individual edit link)
     * is visible, is the sensible landing page instead.
     */
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
