<?php

namespace Tests\Feature\Web;

use App\Models\Category;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Product;
use App\Models\SellerProfile;
use App\Models\User;
use App\Support\Legal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class NavigationRestructureTest extends TestCase
{
    use RefreshDatabase;

    private function onboardedUser(): User
    {
        return User::factory()->create([
            'terms_accepted_at' => now(),
            'terms_version' => Legal::TERMS_VERSION,
            'account_intent' => 'buy',
        ]);
    }

    public function test_the_stores_directory_lists_only_verified_shops_and_supports_search_filter_and_sort(): void
    {
        $category = Category::factory()->create();
        $matching = SellerProfile::factory()->verified()->create(['shop_name' => 'Amina Electronics', 'category_id' => $category->id, 'region' => 'Dar es Salaam']);
        $unverified = SellerProfile::factory()->create(['shop_name' => 'Pending Shop']);
        $otherRegion = SellerProfile::factory()->verified()->create(['shop_name' => 'Arusha Traders', 'region' => 'Arusha']);

        $response = $this->get(route('web.stores'));
        $response->assertOk();
        $response->assertSee('Amina Electronics');
        $response->assertSee('Arusha Traders');
        $response->assertDontSee('Pending Shop');

        $this->get(route('web.stores', ['q' => 'Amina']))
            ->assertSee('Amina Electronics')
            ->assertDontSee('Arusha Traders');

        $this->get(route('web.stores', ['region' => 'dar-es-salaam']))
            ->assertSee('Amina Electronics')
            ->assertDontSee('Arusha Traders');

        $this->get(route('web.stores', ['sort' => 'newest']))->assertOk();
        $this->get(route('web.stores', ['sort' => 'listings']))->assertOk();
    }

    public function test_the_explore_feed_shows_the_newest_products_across_categories(): void
    {
        $seller = SellerProfile::factory()->verified()->create();
        $older = Product::factory()->create(['seller_id' => $seller->id, 'title' => 'Older listing', 'created_at' => now()->subDays(5)]);
        $newer = Product::factory()->create(['seller_id' => $seller->id, 'title' => 'Newest listing', 'created_at' => now()]);

        $response = $this->get(route('web.explore'));

        $response->assertOk();
        $response->assertSeeInOrder(['Newest listing', 'Older listing']);
    }

    public function test_chats_shows_a_signin_prompt_when_logged_out_rather_than_redirecting(): void
    {
        $response = $this->get(route('web.chats'));

        $response->assertOk();
        $response->assertSee(__('site.signin_prompt_chats_title'));
    }

    public function test_profile_shows_a_signin_prompt_when_logged_out_rather_than_redirecting(): void
    {
        $response = $this->get(route('web.profile'));

        $response->assertOk();
        $response->assertSee(__('site.signin_prompt_profile_title'));
    }

    public function test_chats_redirects_straight_to_messages_when_signed_in(): void
    {
        $this->actingAsWebUser($this->onboardedUser())
            ->get(route('web.chats'))
            ->assertRedirect(route('web.account.messages'));
    }

    public function test_profile_redirects_straight_to_the_dashboard_when_signed_in(): void
    {
        $this->actingAsWebUser($this->onboardedUser())
            ->get(route('web.profile'))
            ->assertRedirect(route('web.account.dashboard'));
    }

    public function test_signing_in_from_the_chats_prompt_returns_the_visitor_to_chats_afterwards(): void
    {
        $phone = '+255754000111';
        $this->get(route('web.login', ['redirect' => 'chats']))->assertOk();

        $this->post('/auth/otp/request', ['phone' => $phone]);
        $code = Cache::get('otp:'.$phone);

        $this->post('/auth/otp/verify', ['phone' => $phone, 'code' => $code, 'name' => 'Test User'])
            ->assertRedirect(route('web.account.messages'));
    }

    public function test_an_unsafe_redirect_target_is_silently_ignored(): void
    {
        $response = $this->get(route('web.login', ['redirect' => 'https://evil.example']));

        $response->assertOk();
        $this->assertNotSame('https://evil.example', session('url.intended'));
    }

    public function test_the_bottom_nav_shows_an_unread_badge_only_for_a_signed_in_user_with_unread_messages(): void
    {
        $buyer = $this->onboardedUser();
        $seller = SellerProfile::factory()->verified()->create();
        $conversation = Conversation::factory()->create(['buyer_id' => $buyer->id, 'seller_id' => $seller->id]);
        Message::factory()->create(['conversation_id' => $conversation->id, 'sender_id' => $seller->user_id, 'read_at' => null]);

        $response = $this->actingAsWebUser($buyer)->get(route('web.home'));

        $response->assertOk();
        $response->assertSee('>1<', false);
    }

    public function test_the_bottom_nav_shows_no_badge_for_a_guest(): void
    {
        $response = $this->get(route('web.home'));

        $response->assertOk();
        $response->assertDontSee('bg-sokoni-danger', false);
    }
}
