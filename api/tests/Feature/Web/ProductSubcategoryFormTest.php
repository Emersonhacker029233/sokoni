<?php

namespace Tests\Feature\Web;

use App\Models\Category;
use App\Models\Product;
use App\Models\SellerProfile;
use App\Support\Legal;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sellers can optionally pick a subcategory when posting a product — the
 * form's own "Category" select stays top-level-only, with a second,
 * optional "Subcategory" select that defaults to "use parent category"
 * when nothing more specific is chosen.
 */
class ProductSubcategoryFormTest extends TestCase
{
    use RefreshDatabase;

    private function sellerUser(): \App\Models\User
    {
        $user = \App\Models\User::factory()->create(['terms_accepted_at' => now(), 'terms_version' => Legal::TERMS_VERSION, 'account_intent' => 'sell']);
        SellerProfile::factory()->create(['user_id' => $user->id]);

        return $user;
    }

    public function test_the_create_form_offers_only_top_level_categories_in_the_primary_select(): void
    {
        (new CategorySeeder)->run();
        $electronics = Category::whereNull('parent_id')->where('name_en', 'Electronics')->firstOrFail();
        $tvs = Category::where('parent_id', $electronics->id)->firstOrFail();

        $response = $this->actingAsWebUser($this->sellerUser())->get(route('web.account.shop.products.create', ['lang' => 'en']));

        $response->assertOk();
        // The subcategory shouldn't appear as its own top-level <option> —
        // it only ever shows up inside the JS subcategory map.
        $response->assertDontSee('<option value="'.$tvs->id.'">'.$tvs->name_en.'</option>', false);
        $response->assertSee($electronics->name_en);
        $response->assertSee($tvs->name_en); // present in the embedded subcategories JSON
    }

    public function test_a_seller_can_post_a_product_directly_into_a_subcategory(): void
    {
        (new CategorySeeder)->run();
        $electronics = Category::whereNull('parent_id')->where('name_en', 'Electronics')->firstOrFail();
        $tvs = Category::where('parent_id', $electronics->id)->where('name_en', 'TVs')->firstOrFail();

        $response = $this->actingAsWebUser($this->sellerUser())->post(route('web.account.shop.products.store'), [
            'category_id' => $tvs->id,
            'title' => 'Samsung 43" Smart TV',
            'price' => 450000,
            'stock' => 3,
            'condition' => 'new',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('products', ['title' => 'Samsung 43" Smart TV', 'category_id' => $tvs->id]);
    }

    public function test_a_seller_can_post_a_product_without_a_subcategory_using_only_the_parent(): void
    {
        (new CategorySeeder)->run();
        $electronics = Category::whereNull('parent_id')->where('name_en', 'Electronics')->firstOrFail();

        $response = $this->actingAsWebUser($this->sellerUser())->post(route('web.account.shop.products.store'), [
            'category_id' => $electronics->id,
            'title' => 'Assorted electronics bundle',
            'price' => 20000,
            'stock' => 1,
            'condition' => 'used',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('products', ['title' => 'Assorted electronics bundle', 'category_id' => $electronics->id]);
    }

    public function test_editing_a_product_already_in_a_subcategory_preselects_both_the_parent_and_the_subcategory(): void
    {
        (new CategorySeeder)->run();
        $seller = $this->sellerUser();
        $sellerProfile = $seller->sellerProfile;
        $electronics = Category::whereNull('parent_id')->where('name_en', 'Electronics')->firstOrFail();
        $tvs = Category::where('parent_id', $electronics->id)->where('name_en', 'TVs')->firstOrFail();
        $product = Product::factory()->create(['seller_id' => $sellerProfile->id, 'category_id' => $tvs->id]);

        $response = $this->actingAsWebUser($seller)->get(route('web.account.shop.products.edit', $product));

        $response->assertOk();
        // The Alpine x-data seeds parentId with Electronics' id and childId with TVs' id.
        $response->assertSee("parentId: {$electronics->id}", false);
        $response->assertSee("childId: {$tvs->id}", false);
    }

    public function test_editing_a_product_directly_in_a_top_level_category_has_no_preselected_subcategory(): void
    {
        (new CategorySeeder)->run();
        $seller = $this->sellerUser();
        $sellerProfile = $seller->sellerProfile;
        $electronics = Category::whereNull('parent_id')->where('name_en', 'Electronics')->firstOrFail();
        $product = Product::factory()->create(['seller_id' => $sellerProfile->id, 'category_id' => $electronics->id]);

        $response = $this->actingAsWebUser($seller)->get(route('web.account.shop.products.edit', $product));

        $response->assertOk();
        $response->assertSee("parentId: {$electronics->id}", false);
        $response->assertSee("childId: ''", false);
    }
}
