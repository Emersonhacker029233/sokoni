<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function __invoke(int $product, ?string $slug = null): View|RedirectResponse
    {
        $record = Product::query()
            ->with(['category', 'seller', 'media', 'activeOffer'])
            ->findOrFail($product);

        // Only ever reachable if visible or the request somehow guessed a
        // hidden/pending id — matches the API's own show() rule exactly
        // (visitors browsing the website have no owner-preview concept,
        // unlike the app's signed-in seller).
        abort_unless($record->is_active && ! $record->is_hidden && $record->seller?->isVerified(), 404);

        $canonicalSlug = Str::slug($record->title);
        if ($slug !== $canonicalSlug) {
            return redirect()->route('web.product', ['product' => $record->id, 'slug' => $canonicalSlug], 301);
        }

        $record->increment('views');

        $seller = $record->seller;

        $similar = Product::query()
            ->visible()
            ->where('category_id', $record->category_id)
            ->where('id', '!=', $record->id)
            ->with(['seller', 'media'])
            ->inRandomOrder()
            ->limit(8)
            ->get();

        $moreFromShop = Product::query()
            ->visible()
            ->where('seller_id', $record->seller_id)
            ->where('id', '!=', $record->id)
            ->with(['media'])
            ->latest()
            ->limit(8)
            ->get();

        $reviews = $seller->reviews()
            ->where('is_hidden', false)
            ->with('buyer')
            ->latest()
            ->limit(10)
            ->get();

        $otherListingsCount = Product::visible()->where('seller_id', $record->seller_id)->where('id', '!=', $record->id)->count();

        return view('web.product', [
            'product' => $record,
            'seller' => $seller,
            'offer' => $record->activeOffer,
            'similar' => $similar,
            'moreFromShop' => $moreFromShop,
            'reviews' => $reviews,
            'otherListingsCount' => $otherListingsCount,
            'breadcrumbs' => $this->breadcrumbs($record),
            'title' => $record->title,
            'description' => Str::limit(strip_tags((string) $record->description), 155) ?: "{$record->title} — ".\App\Support\Money::format($record->price).' on Sokoni.',
            'ogType' => 'product',
            'ogImage' => $record->media->first()?->card_path ?? $record->media->first()?->path,
        ]);
    }

    private function breadcrumbs(Product $product): array
    {
        $items = [['label' => 'Sokoni', 'url' => route('web.home')]];
        $catalog = app(\App\Services\Catalog\CategoryCatalogService::class);

        if ($category = $product->category) {
            if ($category->parent_id && $category->parent) {
                $parentSlug = $catalog->slug($category->parent);
                $items[] = ['label' => $category->parent->name(app()->getLocale()), 'url' => route('web.category', $parentSlug)];
                $items[] = ['label' => $category->name(app()->getLocale()), 'url' => route('web.category', [$parentSlug, $catalog->slug($category)])];
            } else {
                $items[] = ['label' => $category->name(app()->getLocale()), 'url' => route('web.category', $catalog->slug($category))];
            }
        }

        $items[] = ['label' => $product->title, 'url' => null];

        return $items;
    }
}
