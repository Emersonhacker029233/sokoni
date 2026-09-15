<?php

namespace App\Services\Catalog;

use App\Models\Category;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

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
 *
 * megaMenuTree() below IS cached, deliberately shaped differently from the
 * pattern that caused that incident: it caches a plain nested array of
 * scalars (ints/strings only), never an Eloquent Collection/Model — nothing
 * with a class to fail to autoload on unserialize, so the same failure mode
 * structurally cannot recur here regardless of whether the underlying cache
 * store issue above is ever root-caused.
 */
class CategoryCatalogService
{
    public const MEGA_MENU_CACHE_KEY = 'web:mega-menu-tree';

    private const MEGA_MENU_TTL_SECONDS = 3600;

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

    /**
     * Every active top-level category with its own active children, for
     * the mega menu — a plain nested array (see the class docblock for why
     * that specific shape matters), cached for an hour since this data
     * changes only when an admin touches the catalog (Category's own
     * saved/deleted hooks bust this immediately when that happens, so the
     * TTL is a ceiling on staleness after a cache-store hiccup, not the
     * normal invalidation path).
     *
     * @return list<array{id: int, name_en: string, name_sw: string, slug: string, icon: ?string, image: ?string, children: list<array{id: int, name_en: string, name_sw: string, slug: string}>}>
     */
    public function megaMenuTree(): array
    {
        return Cache::remember(self::MEGA_MENU_CACHE_KEY, self::MEGA_MENU_TTL_SECONDS, function () {
            return Category::query()
                ->whereNull('parent_id')
                ->where('is_active', true)
                ->with(['children' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order')])
                ->orderBy('sort_order')
                ->get()
                ->map(fn (Category $parent) => [
                    'id' => $parent->id,
                    'name_en' => $parent->name_en,
                    'name_sw' => $parent->name_sw,
                    'slug' => $this->slug($parent),
                    // Part 3 (client feedback): the homepage's noon.com-style
                    // category tiles use this exact same list (same set,
                    // same order as the nav bar) — icon/image are only
                    // needed here, not by the nav bar itself, but adding
                    // them to the one shared, cached call is simpler than
                    // a second near-identical query.
                    'icon' => $parent->icon,
                    'image' => $parent->image,
                    'children' => $parent->children->map(fn (Category $child) => [
                        'id' => $child->id,
                        'name_en' => $child->name_en,
                        'name_sw' => $child->name_sw,
                        'slug' => $this->slug($child),
                    ])->values()->all(),
                ])
                ->values()
                ->all();
        });
    }
}
