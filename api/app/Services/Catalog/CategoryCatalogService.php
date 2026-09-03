<?php

namespace App\Services\Catalog;

use App\Models\Category;
use Illuminate\Support\Collection;

/**
 * Category + live listing count — read on nearly every page (the header,
 * the home page's category grid, the search sidebar).
 *
 * TEMPORARILY UNCACHED: this used to wrap the query in Cache::remember(),
 * caching a Collection of Category models. Production started 500ing with
 * __PHP_Incomplete_Class on unserialize, surviving a full cache-store
 * clear — see DECISIONS.md for the incident writeup. Removed the caching
 * layer entirely to get the site back up; re-add once the actual cache
 * store misconfiguration is confirmed and fixed.
 */
class CategoryCatalogService
{
    /** Active top-level categories with a real count of currently-visible products. */
    public function withCounts(): Collection
    {
        return Category::query()
            ->whereNull('parent_id')
            ->where('is_active', true)
            ->withCount(['products' => fn ($query) => $query->visible()])
            ->orderBy('sort_order')
            ->get();
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
}
