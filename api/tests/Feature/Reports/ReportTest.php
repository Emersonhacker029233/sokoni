<?php

namespace Tests\Feature\Reports;

use App\Models\Product;
use App\Models\Report;
use App\Models\SellerProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_signed_in_user_can_report_a_product(): void
    {
        $product = Product::factory()->create();
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/reports', [
            'reportable_type' => 'product',
            'reportable_id' => $product->id,
            'reason' => 'Prohibited item',
        ])->assertCreated();

        $this->assertDatabaseHas('reports', [
            'reporter_id' => $user->id,
            'reportable_type' => Product::class,
            'reportable_id' => $product->id,
        ]);
    }

    public function test_reporting_a_nonexistent_product_fails_validation(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/reports', [
            'reportable_type' => 'product',
            'reportable_id' => 999999,
            'reason' => 'Prohibited item',
        ])->assertStatus(422);
    }

    /** CLAUDE.md feature 11: three upheld reports against the same content auto-hide it. */
    public function test_three_upheld_reports_auto_hide_a_product(): void
    {
        $product = Product::factory()->create(['is_hidden' => false]);

        for ($i = 0; $i < 3; $i++) {
            $report = Report::factory()->create([
                'reportable_type' => Product::class,
                'reportable_id' => $product->id,
                'status' => 'pending',
            ]);
            $report->forceFill(['status' => 'upheld'])->save();
        }

        $this->assertTrue($product->fresh()->is_hidden);
    }

    public function test_two_upheld_reports_do_not_yet_hide_a_product(): void
    {
        $product = Product::factory()->create(['is_hidden' => false]);

        for ($i = 0; $i < 2; $i++) {
            $report = Report::factory()->create([
                'reportable_type' => Product::class,
                'reportable_id' => $product->id,
                'status' => 'pending',
            ]);
            $report->forceFill(['status' => 'upheld'])->save();
        }

        $this->assertFalse($product->fresh()->is_hidden);
    }

    public function test_offending_user_resolves_through_each_reportable_type(): void
    {
        $product = Product::factory()->create();
        $productReport = Report::factory()->create([
            'reportable_type' => Product::class,
            'reportable_id' => $product->id,
        ]);
        $this->assertEquals($product->seller->user_id, $productReport->offendingUser()->id);

        $seller = SellerProfile::factory()->create();
        $shopReport = Report::factory()->create([
            'reportable_type' => SellerProfile::class,
            'reportable_id' => $seller->id,
        ]);
        $this->assertEquals($seller->user_id, $shopReport->offendingUser()->id);
    }
}
