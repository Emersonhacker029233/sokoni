<?php

namespace Tests\Feature\Web;

use App\Models\Category;
use App\Models\Conversation;
use App\Models\Order;
use App\Models\Product;
use App\Models\SellerProfile;
use App\Models\User;
use App\Services\Catalog\CategoryCatalogService;
use App\Support\Legal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route as RouteFacade;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The regression guard the homepage's __PHP_Incomplete_Class incident (and
 * the follow-up /c/{category} 500) should have caught before deployment.
 * Walks every registered `web.*` GET route via the real router — not a
 * hardcoded URL list, so a future route addition is covered automatically
 * — substituting real seeded records for any route parameter, and asserts
 * nothing comes back as a 5xx. A redirect/403/404 is a legitimate response
 * for an auth-gated or ownership-checked route; only 500+ fails this test.
 */
class AllWebRoutesAvoidServerErrorsTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_public_and_guest_only_web_route_avoids_a_server_error(): void
    {
        $bindings = $this->seedBindings();

        $failures = [];

        foreach ($this->webGetRoutes() as $route) {
            if ($this->requiresAuth($route)) {
                continue;
            }

            $uri = $this->resolveUri($route, $bindings);
            if ($uri === null) {
                continue;
            }

            $response = $this->get($uri);

            if ($response->getStatusCode() >= 500) {
                $failures[] = "{$route->getName()} => {$uri} returned {$response->getStatusCode()}";
            }
        }

        $this->assertEmpty($failures, "Server errors on:\n".implode("\n", $failures));
    }

    public function test_every_authenticated_account_web_route_avoids_a_server_error(): void
    {
        $bindings = $this->seedBindings();
        $this->actingAsWebUser($bindings['buyer']);

        $failures = [];

        foreach ($this->webGetRoutes() as $route) {
            if (! $this->requiresAuth($route)) {
                continue;
            }

            $uri = $this->resolveUri($route, $bindings);
            if ($uri === null) {
                continue;
            }

            $response = $this->get($uri);

            if ($response->getStatusCode() >= 500) {
                $failures[] = "{$route->getName()} => {$uri} returned {$response->getStatusCode()}";
            }
        }

        $this->assertEmpty($failures, "Server errors on:\n".implode("\n", $failures));
    }

    /** /c/{category} with no child segment — the literal reported bug — gets its own explicit assertion, not just swept generically. */
    public function test_a_top_level_category_page_with_no_child_segment_returns_200(): void
    {
        $category = Category::factory()->create(['parent_id' => null, 'is_active' => true]);
        Category::factory()->count(2)->create(['parent_id' => $category->id, 'is_active' => true]);
        $seller = SellerProfile::factory()->verified()->create();
        Product::factory()->create(['seller_id' => $seller->id, 'category_id' => $category->id, 'is_active' => true, 'is_hidden' => false]);

        $slug = app(CategoryCatalogService::class)->slug($category);

        $this->get('/c/'.$slug)->assertOk();
    }

    /** @return array<string, mixed> */
    private function seedBindings(): array
    {
        $category = Category::factory()->create(['parent_id' => null, 'is_active' => true]);
        $child = Category::factory()->create(['parent_id' => $category->id, 'is_active' => true]);

        $publicSeller = SellerProfile::factory()->verified()->create();
        $publicProduct = Product::factory()->create([
            'seller_id' => $publicSeller->id,
            'category_id' => $category->id,
            'is_active' => true,
            'is_hidden' => false,
        ]);

        $buyer = User::factory()->create([
            'terms_accepted_at' => now(),
            'terms_version' => Legal::TERMS_VERSION,
            'account_intent' => 'buy',
        ]);
        $order = Order::factory()->create(['buyer_id' => $buyer->id, 'seller_id' => $publicSeller->id]);
        $conversation = Conversation::factory()->create(['buyer_id' => $buyer->id, 'seller_id' => $publicSeller->id]);

        $catalog = app(CategoryCatalogService::class);

        return [
            'buyer' => $buyer,
            'params' => [
                'category' => $catalog->slug($category),
                'child' => $catalog->slug($child),
                'product' => (string) $publicProduct->id,
                'slug' => Str::slug($publicProduct->title),
                'handle' => $publicSeller->handle,
                'order' => (string) $order->id,
                'conversation' => (string) $conversation->id,
            ],
        ];
    }

    /** @return array<int, \Illuminate\Routing\Route> */
    private function webGetRoutes(): array
    {
        return collect(RouteFacade::getRoutes())
            ->filter(fn ($route) => in_array('GET', $route->methods(), true))
            ->filter(fn ($route) => $route->getName() !== null && str_starts_with($route->getName(), 'web.'))
            ->values()
            ->all();
    }

    private function requiresAuth($route): bool
    {
        return in_array('auth', array_map(
            fn ($m) => explode(':', $m)[0],
            $route->gatherMiddleware(),
        ), true);
    }

    private function resolveUri($route, array $bindings): ?string
    {
        $uri = $route->uri();

        foreach ($route->parameterNames() as $param) {
            if (! array_key_exists($param, $bindings['params'])) {
                return null;
            }
            $uri = preg_replace('#\{'.$param.'\??\}#', $bindings['params'][$param], $uri, 1);
        }

        // Any optional segment left unresolved (e.g. {child?} on a route
        // this test didn't explicitly fill) is dropped, not left literal.
        $uri = preg_replace('#/\{[a-zA-Z_]+\?\}#', '', $uri);

        return '/'.ltrim($uri, '/');
    }
}
