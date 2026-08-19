<?php

namespace App\Services\Catalog;

use App\Models\Category;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Collection;

/**
 * Category + live listing count, cached — read on nearly every page (the
 * header, the home page's category grid, the search sidebar), so this is
 * exactly the kind of query CLAUDE.md's Section 9 asks to cache with a
 * sensible TTL rather than re-running per request.
 */
class CategoryCatalogService
{
    private const TTL_SECONDS = 600;

    /** Active top-level categories with a real count of currently-visible products, cached. */
    public function withCounts(): Collection
    {
        return Cache::remember('web:categories:with-counts', self::TTL_SECONDS, function () {
            return Category::query()
                ->whereNull('parent_id')
                ->where('is_active', true)
                ->withCount(['products' => fn ($query) => $query->visible()])
                ->orderBy('sort_order')
                ->get();
        });
    }

    public function topLevelBySlugOrFail(string $slug): Category
    {
        return $this->withCounts()->first(fn (Category $category) => $this->slug($category) === $slug)
            ?? abort(404);
    }

    /** A subcategory slug within a known parent — /c/{parent}/{child}. */
    public function childBySlugOrFail(Category $parent, string $slug): Category
    {
        return Category::query()
            ->where('parent_id', $parent->id)
            ->where('is_active', true)
            ->get()
            ->first(fn (Category $category) => $this->slug($category) === $slug)
            ?? abort(404);
    }

    public function children(Category $parent): Collection
    {
        return Category::query()
            ->where('parent_id', $parent->id)
            ->where('is_active', true)
            ->withCount(['products' => fn ($query) => $query->visible()])
            ->orderBy('sort_order')
            ->get();
    }

    public function slug(Category $category): string
    {
        return str($category->name_en)->slug()->toString();
    }

    public static function forget(): void
    {
        Cache::forget('web:categories:with-counts');
    }
}
