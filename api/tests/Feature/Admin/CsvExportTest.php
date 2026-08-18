<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Filament\Resources\Products\Pages\ListProducts;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Support\CsvExporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CsvExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_csv_exporter_streams_a_well_formed_csv(): void
    {
        $response = CsvExporter::stream(
            'test.csv',
            ['ID', 'Name'],
            [[1, 'Alpha'], [2, 'Beta']],
        );

        ob_start();
        $response->sendContent();
        $csv = ob_get_clean();

        $this->assertSame("ID,Name\n1,Alpha\n2,Beta\n", $csv);
        $this->assertSame('text/csv', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment; filename="test.csv"', $response->headers->get('Content-Disposition'));
    }

    public function test_the_export_action_is_visible_and_the_products_export_query_respects_the_current_status_filter(): void
    {
        $admin = User::factory()->admin()->create();
        $hidden = Product::factory()->create(['title' => 'Hidden Widget', 'is_hidden' => true]);
        $live = Product::factory()->create(['title' => 'Live Widget', 'is_hidden' => false, 'is_active' => true]);

        $this->actingAsAdmin($admin);

        $component = Livewire::test(ListProducts::class)
            ->assertActionExists('exportCsv')
            ->filterTable('status', 'hidden');

        $exportedTitles = $component->instance()->getFilteredSortedTableQuery()->pluck('title');

        $this->assertTrue($exportedTitles->contains('Hidden Widget'));
        $this->assertFalse($exportedTitles->contains('Live Widget'));
    }

    public function test_the_orders_export_action_exists_and_the_query_includes_real_orders(): void
    {
        $admin = User::factory()->admin()->create();
        $order = Order::factory()->create();

        $this->actingAsAdmin($admin);

        $component = Livewire::test(ListOrders::class)->assertActionExists('exportCsv');

        $codes = $component->instance()->getFilteredSortedTableQuery()->pluck('code');
        $this->assertTrue($codes->contains($order->code));
    }

    public function test_the_users_export_action_exists_and_is_usable_by_an_admin(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAsAdmin($admin);

        Livewire::test(ListUsers::class)->assertActionExists('exportCsv');
    }
}
