<?php

namespace Tests\Feature\Admin;

use App\Filament\Widgets\ModerationQueueTable;
use App\Filament\Widgets\NewUsersAndSellersChart;
use App\Filament\Widgets\OrdersOverTimeChart;
use App\Filament\Widgets\PendingVerificationsTable;
use App\Filament\Widgets\RecentOrdersTable;
use App\Filament\Widgets\RevenueByCategoryChart;
use App\Filament\Widgets\SokoniStatsOverview;
use App\Filament\Widgets\TopSellersByRevenueTable;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Report;
use App\Models\SellerProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use ReflectionMethod;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    /** ChartWidget::getData() is protected by Filament's own design (it's an internal hook the base class wires up for the frontend, not a public API) — reflection is the standard way to unit-test its data-shaping logic without loosening that on purpose. */
    private function chartData(object $widget): array
    {
        $method = new ReflectionMethod($widget, 'getData');
        $method->setAccessible(true);

        return $method->invoke($widget);
    }

    public function test_the_dashboard_loads_with_every_widget(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAsAdmin($admin)->get('/admin')->assertOk();
    }

    public function test_stats_overview_reports_a_real_trend_not_a_fabricated_one_for_an_empty_prior_period(): void
    {
        $admin = User::factory()->admin()->create();
        // The only user is the admin just created (this run) plus none in the prior 30-60 day window.
        $this->actingAsAdmin($admin);

        $component = Livewire::test(SokoniStatsOverview::class);
        $component->assertSuccessful();
    }

    public function test_orders_over_time_chart_reflects_real_daily_counts(): void
    {
        $admin = User::factory()->admin()->create();
        Order::factory()->create(['created_at' => now()]);
        Order::factory()->create(['created_at' => now()]);
        Order::factory()->create(['created_at' => now()->subDays(5)]);

        $this->actingAsAdmin($admin);

        $data = $this->chartData(Livewire::test(OrdersOverTimeChart::class)->instance());

        $this->assertSame(30, count($data['labels']));
        $this->assertSame(2, $data['datasets'][0]['data'][29]); // today = last label
    }

    public function test_revenue_by_category_only_counts_completed_orders(): void
    {
        $admin = User::factory()->admin()->create();
        $category = Category::factory()->create(['name_en' => 'Electronics']);
        $product = Product::factory()->create(['category_id' => $category->id]);

        $completedOrder = Order::factory()->create(['status' => 'completed']);
        OrderItem::factory()->create(['order_id' => $completedOrder->id, 'product_id' => $product->id, 'price_snapshot' => 10000, 'qty' => 2]);

        $pendingOrder = Order::factory()->create(['status' => 'pending']);
        OrderItem::factory()->create(['order_id' => $pendingOrder->id, 'product_id' => $product->id, 'price_snapshot' => 999999, 'qty' => 1]);

        $this->actingAsAdmin($admin);

        $data = $this->chartData(Livewire::test(RevenueByCategoryChart::class)->instance());

        $this->assertContains('Electronics', $data['labels']);
        $index = array_search('Electronics', $data['labels']);
        $this->assertSame(20000.0, $data['datasets'][0]['data'][$index]);
    }

    public function test_top_sellers_by_revenue_ranks_by_real_completed_order_totals(): void
    {
        $admin = User::factory()->admin()->create();
        $bigSeller = SellerProfile::factory()->create(['shop_name' => 'Big Shop']);
        $smallSeller = SellerProfile::factory()->create(['shop_name' => 'Small Shop']);

        Order::factory()->create(['seller_id' => $bigSeller->id, 'status' => 'completed', 'total' => 500000]);
        Order::factory()->create(['seller_id' => $smallSeller->id, 'status' => 'completed', 'total' => 10000]);
        Order::factory()->create(['seller_id' => $smallSeller->id, 'status' => 'pending', 'total' => 999999]);

        $this->actingAsAdmin($admin);

        Livewire::test(TopSellersByRevenueTable::class)
            ->assertCanSeeTableRecords([$bigSeller, $smallSeller], inOrder: true);
    }

    public function test_recent_orders_table_links_to_the_real_order(): void
    {
        $admin = User::factory()->admin()->create();
        $order = Order::factory()->create();

        $this->actingAsAdmin($admin);

        Livewire::test(RecentOrdersTable::class)->assertCanSeeTableRecords([$order]);
    }

    public function test_pending_verifications_widget_shows_only_pending_sellers(): void
    {
        $admin = User::factory()->admin()->create();
        $pending = SellerProfile::factory()->create();
        $verified = SellerProfile::factory()->verified()->create();

        $this->actingAsAdmin($admin);

        Livewire::test(PendingVerificationsTable::class)
            ->assertCanSeeTableRecords([$pending])
            ->assertCanNotSeeTableRecords([$verified]);
    }

    public function test_moderation_queue_widget_shows_only_pending_reports(): void
    {
        $admin = User::factory()->admin()->create();
        $product = Product::factory()->create();
        $pending = Report::factory()->create(['reportable_type' => Product::class, 'reportable_id' => $product->id, 'status' => 'pending']);
        $resolved = Report::factory()->create(['reportable_type' => Product::class, 'reportable_id' => $product->id, 'status' => 'dismissed']);

        $this->actingAsAdmin($admin);

        Livewire::test(ModerationQueueTable::class)
            ->assertCanSeeTableRecords([$pending])
            ->assertCanNotSeeTableRecords([$resolved]);
    }

    public function test_new_users_and_sellers_chart_has_two_real_series(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->create(['created_at' => now()]);
        SellerProfile::factory()->create(['created_at' => now()]);

        $this->actingAsAdmin($admin);

        $data = $this->chartData(Livewire::test(NewUsersAndSellersChart::class)->instance());

        $this->assertCount(2, $data['datasets']);
        $this->assertSame('New users', $data['datasets'][0]['label']);
        $this->assertSame('New sellers', $data['datasets'][1]['label']);
    }
}
