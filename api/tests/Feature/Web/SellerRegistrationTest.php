<?php

namespace Tests\Feature\Web;

use App\Models\Category;
use App\Models\SellerProfile;
use App\Models\User;
use App\Support\Legal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SellerRegistrationTest extends TestCase
{
    use RefreshDatabase;

    private function onboardedBuyer(): User
    {
        return User::factory()->create([
            'terms_accepted_at' => now(),
            'terms_version' => Legal::TERMS_VERSION,
            'account_intent' => 'buy',
        ]);
    }

    private function validPayload(Category $category): array
    {
        return [
            'shop_name' => 'Amina Electronics',
            'handle' => 'amina_electronics',
            'category_id' => $category->id,
            'bio' => 'Quality phones and accessories.',
            'whatsapp' => '+255754123456',
            'region' => 'Dar es Salaam',
            'district' => 'Kinondoni',
            'address' => 'Mlimani City, Sam Nujoma Road',
            'nida_number' => str_repeat('1', 20),
            'nida_image' => UploadedFile::fake()->image('nida.jpg'),
            'licence_file' => UploadedFile::fake()->create('licence.pdf', 200, 'application/pdf'),
        ];
    }

    public function test_the_intent_screen_offers_buy_sell_and_a_quiet_later_link(): void
    {
        $user = User::factory()->create(['terms_accepted_at' => now(), 'terms_version' => Legal::TERMS_VERSION]);

        $response = $this->actingAsWebUser($user)->get(route('web.auth.intent'));

        $response->assertOk();
        $response->assertSee(__('site.auth_intent_buy'));
        $response->assertSee(__('site.auth_intent_sell'));
        $response->assertSee(__('site.auth_intent_later'));
    }

    public function test_choosing_sell_leads_all_the_way_to_the_registration_form(): void
    {
        $user = User::factory()->create(['terms_accepted_at' => now(), 'terms_version' => Legal::TERMS_VERSION]);
        $this->actingAsWebUser($user);

        $this->post(route('web.auth.intent.store'), ['intent' => 'sell'])
            ->assertRedirect(route('web.account.shop'));

        $this->get(route('web.account.shop'))
            ->assertRedirect(route('web.account.shop.register'));

        $this->get(route('web.account.shop.register'))->assertOk();
    }

    public function test_a_non_seller_can_view_the_registration_form(): void
    {
        $response = $this->actingAsWebUser($this->onboardedBuyer())->get(route('web.account.shop.register'));

        $response->assertOk();
        $response->assertSee(__('site.seller_register_title'));
        $response->assertSee(__('site.seller_section_business'));
        $response->assertSee(__('site.seller_section_location'));
        $response->assertSee(__('site.seller_section_identity'));
        $response->assertSee(__('site.seller_section_licence'));
    }

    public function test_an_existing_seller_visiting_the_form_is_redirected_to_their_dashboard(): void
    {
        $user = $this->onboardedBuyer();
        SellerProfile::factory()->create(['user_id' => $user->id]);

        $this->actingAsWebUser($user)->get(route('web.account.shop.register'))
            ->assertRedirect(route('web.account.shop'));
    }

    public function test_a_non_seller_sees_start_selling_in_the_profile_nav(): void
    {
        $response = $this->actingAsWebUser($this->onboardedBuyer())->get(route('web.account.dashboard'));

        $response->assertOk();
        $response->assertSee(route('web.account.shop.register', absolute: false), false);
    }

    public function test_submitting_valid_details_creates_a_pending_seller_profile_with_uploaded_files(): void
    {
        Storage::fake('public');
        Http::fake(); // Should never even be called — a real address with no lat/lng still shouldn't hit geocoding unless both are truly absent... see next test for that path explicitly.
        $user = $this->onboardedBuyer();
        $category = Category::factory()->create();

        $response = $this->actingAsWebUser($user)->post(route('web.account.shop.register.store'), $this->validPayload($category));

        $response->assertRedirect(route('web.account.shop'));

        $seller = SellerProfile::where('user_id', $user->id)->firstOrFail();
        $this->assertSame('Amina Electronics', $seller->shop_name);
        $this->assertSame('amina_electronics', $seller->handle);
        $this->assertSame('pending', $seller->status);
        $this->assertSame('Dar es Salaam', $seller->region);
        $this->assertSame('Kinondoni', $seller->district);
        $this->assertNotNull($seller->nida_image);
        $this->assertNotNull($seller->licence_file);
        Storage::disk('public')->assertExists($seller->nida_image);
        Storage::disk('public')->assertExists($seller->licence_file);
    }

    public function test_submitting_without_a_map_pin_triggers_best_effort_server_side_geocoding(): void
    {
        Storage::fake('public');
        Http::fake([
            'nominatim.openstreetmap.org/*' => Http::response([
                ['lat' => '-6.7924', 'lon' => '39.2083'],
            ], 200),
        ]);
        $user = $this->onboardedBuyer();
        $category = Category::factory()->create();

        $this->actingAsWebUser($user)->post(route('web.account.shop.register.store'), $this->validPayload($category));

        $seller = SellerProfile::where('user_id', $user->id)->firstOrFail();
        $this->assertEqualsWithDelta(-6.7924, (float) $seller->lat, 0.0001);
        $this->assertEqualsWithDelta(39.2083, (float) $seller->lng, 0.0001);
    }

    public function test_a_failed_geocode_never_blocks_registration(): void
    {
        Storage::fake('public');
        Http::fake(['nominatim.openstreetmap.org/*' => Http::response([], 500)]);
        $user = $this->onboardedBuyer();
        $category = Category::factory()->create();

        $response = $this->actingAsWebUser($user)->post(route('web.account.shop.register.store'), $this->validPayload($category));

        $response->assertRedirect(route('web.account.shop'));
        $seller = SellerProfile::where('user_id', $user->id)->firstOrFail();
        $this->assertSame('pending', $seller->status);
        $this->assertNull($seller->lat);
        $this->assertNull($seller->lng);
    }

    public function test_registration_requires_the_nida_upload_but_not_a_licence(): void
    {
        Storage::fake('public');
        $user = $this->onboardedBuyer();
        $category = Category::factory()->create();
        $payload = $this->validPayload($category);
        unset($payload['nida_image'], $payload['licence_file']);

        $response = $this->actingAsWebUser($user)->post(route('web.account.shop.register.store'), $payload);

        $response->assertSessionHasErrors(['nida_image']);
        $response->assertSessionDoesntHaveErrors(['licence_file']);
        $this->assertDatabaseMissing('seller_profiles', ['user_id' => $user->id]);
    }

    public function test_registration_succeeds_with_nida_but_no_licence_file(): void
    {
        Storage::fake('public');
        Http::fake();
        $user = $this->onboardedBuyer();
        $category = Category::factory()->create();
        $payload = $this->validPayload($category);
        unset($payload['licence_file']);

        $response = $this->actingAsWebUser($user)->post(route('web.account.shop.register.store'), $payload);

        $response->assertRedirect(route('web.account.shop'));
        $seller = SellerProfile::where('user_id', $user->id)->firstOrFail();
        $this->assertSame('pending', $seller->status);
        $this->assertNotNull($seller->nida_image);
        $this->assertNull($seller->licence_file);
    }

    public function test_a_user_who_is_already_a_seller_cannot_submit_a_second_registration(): void
    {
        Storage::fake('public');
        $user = $this->onboardedBuyer();
        SellerProfile::factory()->create(['user_id' => $user->id]);
        $category = Category::factory()->create();

        $this->actingAsWebUser($user)
            ->post(route('web.account.shop.register.store'), $this->validPayload($category))
            ->assertForbidden();
    }

    public function test_the_shop_dashboard_shows_the_pending_review_message_with_a_timeframe(): void
    {
        $user = $this->onboardedBuyer();
        SellerProfile::factory()->create(['user_id' => $user->id, 'status' => 'pending']);

        $response = $this->actingAsWebUser($user)->get(route('web.account.shop'));

        $response->assertOk();
        $response->assertSee(__('site.seller_pending_title'));
        $response->assertSee('1-2', false);
    }
}
