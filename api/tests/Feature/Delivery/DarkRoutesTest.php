<?php

namespace Tests\Feature\Delivery;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * With the switches off, every new route must answer exactly like a route
 * that does not exist, signed in or not, whatever the id: no
 * "Unauthenticated", no "No query results for model", no validation error.
 */
class DarkRoutesTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, array{string, string}> */
    public static function routes(): array
    {
        return [
            'config' => ['GET', '/api/delivery/config'],
            'quote' => ['GET', '/api/delivery/quote?seller_id=1&lat=1&lng=1'],
            'order view' => ['GET', '/api/delivery/orders/999'],
            'live location' => ['GET', '/api/delivery/orders/999/location'],
            'pay' => ['POST', '/api/delivery/orders/999/pay'],
            'returns list' => ['GET', '/api/returns'],
            'request a return' => ['POST', '/api/orders/999/returns'],
            'return action' => ['POST', '/api/returns/999/accept'],
            'return policy' => ['PUT', '/api/shop/return-policy'],
            'webhook' => ['POST', '/api/webhooks/bolt'],
        ];
    }

    #[DataProvider('routes')]
    public function test_off_means_not_found_for_guests_and_users_alike(string $method, string $uri): void
    {
        config(['delivery.enabled' => false, 'delivery.returns_enabled' => false]);
        $path = ltrim(parse_url($uri, PHP_URL_PATH), '/');

        $this->json($method, $uri)->assertNotFound()->assertJsonPath('message', "The route {$path} could not be found.");
        $this->actingAs(User::factory()->create())->json($method, $uri)->assertNotFound()->assertJsonPath('message', "The route {$path} could not be found.");
    }

    #[DataProvider('routes')]
    public function test_returns_off_alone_hides_the_return_routes(string $method, string $uri): void
    {
        config(['delivery.enabled' => true, 'delivery.returns_enabled' => false]);
        if (! str_contains($uri, 'return')) {
            $this->markTestSkipped('a delivery route');
        }

        $this->actingAs(User::factory()->create())->json($method, $uri)->assertNotFound();
    }
}
