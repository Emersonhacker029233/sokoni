<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\Offers\Pages\ListOffers;
use App\Filament\Resources\Showcases\Pages\ListShowcases;
use App\Filament\Resources\Updates\Pages\ListUpdates;
use App\Models\Offer;
use App\Models\Showcase;
use App\Models\Update;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SocialModerationResourcesTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_admin_can_load_updates_offers_and_showcases(): void
    {
        $admin = User::factory()->admin()->create();
        Update::factory()->create();
        Offer::factory()->create();
        Showcase::factory()->create();

        $this->actingAsAdmin($admin);
        $this->get('/admin/updates')->assertOk();
        $this->get('/admin/offers')->assertOk();
        $this->get('/admin/showcases')->assertOk();
    }

    public function test_an_offers_status_reflects_whether_it_is_currently_active(): void
    {
        $admin = User::factory()->admin()->create();
        $active = Offer::factory()->create(['starts_at' => now()->subHour(), 'ends_at' => now()->addDay()]);
        $ended = Offer::factory()->create(['starts_at' => now()->subDays(3), 'ends_at' => now()->subDay()]);

        $this->assertTrue($active->isActive());
        $this->assertFalse($ended->isActive());

        $this->actingAsAdmin($admin);
        Livewire::test(ListOffers::class)->assertCanSeeTableRecords([$active, $ended]);
    }

    public function test_an_admin_can_remove_an_offer_update_and_showcase(): void
    {
        $admin = User::factory()->admin()->create();
        $offer = Offer::factory()->create();
        $update = Update::factory()->create();
        $showcase = Showcase::factory()->create();

        $this->actingAsAdmin($admin);

        Livewire::test(ListOffers::class)->callTableAction('remove', $offer)->assertHasNoTableActionErrors();
        Livewire::test(ListUpdates::class)->callTableAction('remove', $update)->assertHasNoTableActionErrors();
        Livewire::test(ListShowcases::class)->callTableAction('remove', $showcase)->assertHasNoTableActionErrors();

        $this->assertDatabaseMissing('offers', ['id' => $offer->id]);
        $this->assertDatabaseMissing('updates', ['id' => $update->id]);
        $this->assertDatabaseMissing('showcases', ['id' => $showcase->id]);
    }
}
