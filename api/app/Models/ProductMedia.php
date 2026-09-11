<?php

namespace App\Models;

use App\Services\Media\ImageVariants;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

#[Fillable(['product_id', 'type', 'path', 'thumb_path', 'card_path', 'duration', 'sort'])]
class ProductMedia extends Model
{
    use HasFactory;

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function isVideo(): bool
    {
        return $this->type === 'video';
    }

    /**
     * D1 (tester feedback): "media with individual image removal" — a
     * removal that only drops the DB row leaks the physical file(s) on
     * `public` disk forever. `Api\ProductMediaController::destroy()` and
     * `Web\Account\ShopProductMediaController::destroy()` both already
     * did this cleanup inline, identically; `Filament\...\ProductMediaTable`
     * did not (a real, pre-existing leak this method also fixes there),
     * and the admin product-edit page's own media manager needs the exact
     * same thing — one shared place rather than a fourth copy.
     */
    public function deleteWithFiles(): void
    {
        foreach ([$this->path, $this->thumb_path, $this->card_path] as $url) {
            if ($url) {
                $relative = str($url)->after(Storage::disk('public')->url(''));
                Storage::disk('public')->delete($relative);
            }
        }

        $this->delete();
    }

    /**
     * D1: the admin edit page's own photo manager (MediaRelationManager)
     * needs the exact same thumb/card/full pipeline the seller/app upload
     * paths use — pulled out as its own method (rather than inlined in
     * the RelationManager's action closure) so it's directly unit-testable
     * without going through Livewire's file-upload test synth, which has
     * a known limitation with files inside a mounted Action's form data
     * (as opposed to a page-level form, where it works fine — see
     * BannerResourceTest) that isn't specific to anything this app does.
     */
    public static function createFromUpload(Product $product, UploadedFile $file): self
    {
        $directory = "products/{$product->id}";
        $variants = ImageVariants::generate($file, $directory);

        return $product->media()->create([
            'type' => 'image',
            'path' => Storage::disk('public')->url($variants['full']),
            'card_path' => Storage::disk('public')->url($variants['card']),
            'thumb_path' => Storage::disk('public')->url($variants['thumb']),
            'sort' => $product->media()->count(),
        ]);
    }
}
