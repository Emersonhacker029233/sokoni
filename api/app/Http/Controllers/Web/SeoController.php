<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\SellerProfile;
use App\Services\Catalog\CategoryCatalogService;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * "Split: products, shops, categories" (CLAUDE.md Section 8) — a plain
 * sitemap index pointing at three sub-sitemaps rather than one file, since
 * the product catalog alone can outgrow a single sitemap's practical size
 * well before the platform outgrows this simple a setup.
 */
class SeoController extends Controller
{
    private const TTL_SECONDS = 3600;

    public function sitemapIndex(): Response
    {
        $xml = view('web.sitemap.index', [
            'sitemaps' => [
                route('web.sitemap.products'),
                route('web.sitemap.shops'),
                route('web.sitemap.categories'),
            ],
        ])->render();

        return response($xml, 200)->header('Content-Type', 'application/xml');
    }

    public function sitemapProducts(): Response
    {
        $xml = Cache::remember('web:sitemap:products', self::TTL_SECONDS, function () {
            $urls = Product::query()->visible()->select(['id', 'title', 'updated_at'])->cursor()
                ->map(fn (Product $product) => [
                    'loc' => route('web.product', ['product' => $product->id, 'slug' => Str::slug($product->title)]),
                    'lastmod' => $product->updated_at?->toAtomString(),
                ]);

            return view('web.sitemap.urlset', ['urls' => $urls])->render();
        });

        return response($xml, 200)->header('Content-Type', 'application/xml');
    }

    public function sitemapShops(): Response
    {
        $xml = Cache::remember('web:sitemap:shops', self::TTL_SECONDS, function () {
            $urls = SellerProfile::query()->verified()->select(['handle', 'updated_at'])->cursor()
                ->map(fn (SellerProfile $seller) => [
                    'loc' => route('web.shop', $seller->handle),
                    'lastmod' => $seller->updated_at?->toAtomString(),
                ]);

            return view('web.sitemap.urlset', ['urls' => $urls])->render();
        });

        return response($xml, 200)->header('Content-Type', 'application/xml');
    }

    public function sitemapCategories(): Response
    {
        $xml = Cache::remember('web:sitemap:categories', self::TTL_SECONDS, function () {
            $catalog = app(CategoryCatalogService::class);
            $urls = collect();

            foreach (Category::where('is_active', true)->whereNull('parent_id')->get() as $parent) {
                $urls->push(['loc' => route('web.category', $catalog->slug($parent)), 'lastmod' => $parent->updated_at?->toAtomString()]);

                foreach ($catalog->children($parent) as $child) {
                    $urls->push(['loc' => route('web.category', [$catalog->slug($parent), $catalog->slug($child)]), 'lastmod' => $child->updated_at?->toAtomString()]);
                }
            }

            return view('web.sitemap.urlset', ['urls' => $urls])->render();
        });

        return response($xml, 200)->header('Content-Type', 'application/xml');
    }

    public function robots(): Response
    {
        $lines = [
            'User-agent: *',
            'Disallow: /account/',
            'Disallow: /auth/',
            'Disallow: /login',
            'Disallow: /search',
            '',
            'Sitemap: '.route('web.sitemap'),
        ];

        return response(implode("\n", $lines), 200)->header('Content-Type', 'text/plain');
    }

    /**
     * Part 2 (client feedback): "shared product links don't open" — the
     * app's Android manifest declares an autoVerify intent-filter for
     * this host (`/p`, plus `/products` for links shared before this
     * fix), and Android's Digital Asset Links check fetches exactly this
     * URL to confirm the app named here really is allowed to intercept
     * them, before it ever hands a link to the app instead of a browser.
     */
    public function assetlinks(): \Illuminate\Http\JsonResponse
    {
        $json = [[
            'relation' => ['delegate_permission/common.handle_all_urls'],
            'target' => [
                'namespace' => 'android_app',
                'package_name' => config('sokoni.android_app.package_name'),
                'sha256_cert_fingerprints' => [config('sokoni.android_app.sha256_cert_fingerprint')],
            ],
        ]];

        return response()->json($json);
    }
}
