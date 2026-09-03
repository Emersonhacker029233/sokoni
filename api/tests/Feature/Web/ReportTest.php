<?php

namespace Tests\Feature\Web;

use App\Models\Product;
use App\Models\Report;
use App\Models\SellerProfile;
use App\Models\User;
use App\Support\Legal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The website had no report mechanism at all — a genuinely absent
 * CLAUDE.md feature 11 requirement ("Report button on every product,
 * shop and message"), not just a missing-feedback gap like reviews
 * (tester feedback A3's audit). Reuses the API's own StoreReportRequest,
 * so a report filed from the website reaches the identical moderation
 * queue. Covers product and shop reporting; message-level reporting is
 * deliberately deferred — see DECISIONS.md.
 */
class ReportTest extends TestCase
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

    public function test_the_product_page_shows_a_report_button_for_signed_in_users(): void
    {
        $seller = SellerProfile::factory()->verified()->create();
        $product = Product::factory()->create(['seller_id' => $seller->id]);

        $response = $this->actingAsWebUser($this->onboardedUser())->get(
            route('web.product', ['product' => $product->id, 'slug' => \Illuminate\Support\Str::slug($product->title)])
        );

        $response->assertOk();
        $response->assertSee(route('web.account.reports.store'), false);
    }

    public function test_a_signed_in_user_can_report_a_product_and_sees_a_confirmation(): void
    {
        $user = $this->onboardedUser();
        $seller = SellerProfile::factory()->verified()->create();
        $product = Product::factory()->create(['seller_id' => $seller->id]);

        // The real (canonical, slugged) product URL — using the bare /p/{id}
        // here would trigger ProductController's own 301 canonical-slug
        // redirect as an *extra* hop, which ages out the flash session data
        // one request too early and has nothing to do with the report
        // feature itself; a real visitor reporting from the page they're
        // actually looking at is always already on the canonical URL.
        $productUrl = route('web.product', ['product' => $product->id, 'slug' => \Illuminate\Support\Str::slug($product->title)]);

        $response = $this->actingAsWebUser($user)->from($productUrl)->post(route('web.account.reports.store'), [
            'reportable_type' => 'product',
            'reportable_id' => $product->id,
            'reason' => 'Spam',
        ]);

        $response->assertRedirect($productUrl);
        $this->assertDatabaseHas('reports', [
            'reporter_id' => $user->id,
            'reportable_type' => Product::class,
            'reportable_id' => $product->id,
            'reason' => 'Spam',
        ]);

        $this->followRedirects($response)->assertSee(__('site.report_submitted'));
    }

    public function test_a_signed_in_user_can_report_a_shop(): void
    {
        $user = $this->onboardedUser();
        $seller = SellerProfile::factory()->verified()->create();

        $this->actingAsWebUser($user)->post(route('web.account.reports.store'), [
            'reportable_type' => 'shop',
            'reportable_id' => $seller->id,
            'reason' => 'Prohibited item',
        ])->assertRedirect();

        $this->assertDatabaseHas('reports', [
            'reporter_id' => $user->id,
            'reportable_type' => SellerProfile::class,
            'reportable_id' => $seller->id,
        ]);
    }

    public function test_reporting_a_nonexistent_product_fails_validation(): void
    {
        $this->actingAsWebUser($this->onboardedUser())->post(route('web.account.reports.store'), [
            'reportable_type' => 'product',
            'reportable_id' => 999999,
            'reason' => 'Spam',
        ])->assertSessionHasErrors('reportable_id');

        $this->assertSame(0, Report::count());
    }

    public function test_a_signed_out_visitor_is_sent_to_login_when_reporting(): void
    {
        $seller = SellerProfile::factory()->verified()->create();
        $product = Product::factory()->create(['seller_id' => $seller->id]);

        $this->post(route('web.account.reports.store'), [
            'reportable_type' => 'product',
            'reportable_id' => $product->id,
            'reason' => 'Spam',
        ])->assertRedirect(route('web.login'));
    }
}
