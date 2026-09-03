<?php

namespace Tests\Feature\Admin;

use App\Filament\Pages\BulkSms;
use App\Models\ActivityLog;
use App\Models\SellerProfile;
use App\Models\SmsBlast;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Livewire\Livewire;
use Tests\TestCase;

/** C6: admin bulk-SMS tool — audience selection, cap enforcement, and blast queuing (sending is ProcessSmsBlastsTest's job). */
class BulkSmsTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_admin_can_load_the_page(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAsAdmin($admin)->get('/admin/bulk-sms')->assertOk();
    }

    public function test_verified_sellers_audience_counts_only_verified_sellers(): void
    {
        SellerProfile::factory()->verified()->create();
        SellerProfile::factory()->create(); // pending — excluded
        User::factory()->count(3)->create(); // plain buyers — excluded

        $this->actingAsAdmin(User::factory()->admin()->create());

        $count = Livewire::test(BulkSms::class)->set('audience', 'verified_sellers')->instance()->recipientCount();

        $this->assertSame(1, $count);
    }

    public function test_buyers_audience_excludes_anyone_with_a_seller_profile(): void
    {
        SellerProfile::factory()->verified()->create();
        User::factory()->count(2)->create();

        // The signed-in admin itself has no seller profile either, so it
        // also matches "buyers" — accounted for below rather than excluded
        // as a special case, since a real admin account genuinely would too.
        $this->actingAsAdmin(User::factory()->admin()->create());

        $count = Livewire::test(BulkSms::class)->set('audience', 'buyers')->instance()->recipientCount();

        $this->assertSame(3, $count);
    }

    public function test_a_banned_user_is_never_counted_in_any_audience(): void
    {
        User::factory()->create(['banned_at' => now()]);
        $this->actingAsAdmin(User::factory()->admin()->create());

        $count = Livewire::test(BulkSms::class)->set('audience', 'all')->instance()->recipientCount();

        // Just the admin — the banned user is excluded.
        $this->assertSame(1, $count);
    }

    public function test_a_user_with_no_phone_is_never_counted(): void
    {
        User::factory()->create(['phone' => null]);
        $this->actingAsAdmin(User::factory()->admin()->create());

        $count = Livewire::test(BulkSms::class)->set('audience', 'all')->instance()->recipientCount();

        // Just the admin — the phoneless user is excluded.
        $this->assertSame(1, $count);
    }

    public function test_consent_only_filters_to_marketing_consent_users(): void
    {
        User::factory()->create(['marketing_consent' => true]);
        User::factory()->create(['marketing_consent' => false]);

        $this->actingAsAdmin(User::factory()->admin()->create());

        $count = Livewire::test(BulkSms::class)
            ->set('audience', 'all')
            ->set('consentOnly', true)
            ->instance()
            ->recipientCount();

        $this->assertSame(1, $count);
    }

    public function test_confirm_is_refused_with_an_empty_message(): void
    {
        User::factory()->create();
        $this->actingAsAdmin(User::factory()->admin()->create());

        Livewire::test(BulkSms::class)
            ->set('audience', 'all')
            ->set('message', '   ')
            ->call('confirm')
            ->assertSet('confirming', false);
    }

    public function test_confirm_is_refused_with_no_matching_recipients(): void
    {
        $this->actingAsAdmin(User::factory()->admin()->create());

        Livewire::test(BulkSms::class)
            ->set('audience', 'verified_sellers')
            ->set('message', 'Hello')
            ->call('confirm')
            ->assertSet('confirming', false);
    }

    public function test_confirm_is_refused_when_the_audience_exceeds_the_cap(): void
    {
        Config::set('sokoni.max_sms_blast_size', 2);
        User::factory()->count(3)->create();
        $this->actingAsAdmin(User::factory()->admin()->create());

        Livewire::test(BulkSms::class)
            ->set('audience', 'all')
            ->set('message', 'Hello')
            ->call('confirm')
            ->assertSet('confirming', false);
    }

    public function test_confirm_succeeds_and_send_queues_a_blast_with_a_snapshotted_recipient_per_user(): void
    {
        $buyers = User::factory()->count(3)->create();
        $admin = User::factory()->admin()->create();
        $this->actingAsAdmin($admin);

        Livewire::test(BulkSms::class)
            ->set('audience', 'all')
            ->set('message', 'New Kids category is live!')
            ->call('confirm')
            ->assertSet('confirming', true)
            ->call('send')
            ->assertSet('confirming', false)
            ->assertSet('message', '');

        $blast = SmsBlast::firstOrFail();
        $this->assertSame($admin->id, $blast->sender_id);
        $this->assertSame('all', $blast->audience);
        $this->assertSame('New Kids category is live!', $blast->message);
        $this->assertSame(4, $blast->total_count); // 3 buyers + the admin itself, since 'all' has no seller-exclusion
        $this->assertSame(4, $blast->recipients()->count());
        foreach ($buyers as $buyer) {
            $this->assertDatabaseHas('sms_blast_recipients', ['sms_blast_id' => $blast->id, 'user_id' => $buyer->id, 'status' => 'pending']);
        }
    }

    public function test_sending_a_blast_records_an_activity_log_entry(): void
    {
        User::factory()->count(2)->create();
        $admin = User::factory()->admin()->create();
        $this->actingAsAdmin($admin);

        Livewire::test(BulkSms::class)
            ->set('audience', 'all')
            ->set('message', 'Promo!')
            ->call('confirm')
            ->call('send');

        $log = ActivityLog::where('action', 'marketing.sms_blast_queued')->firstOrFail();
        $this->assertSame($admin->id, $log->causer_id);
        $this->assertSame(3, $log->meta['recipient_count']); // 2 buyers + the admin itself under 'all'
    }

    public function test_message_units_is_estimated_from_character_length(): void
    {
        $this->actingAsAdmin(User::factory()->admin()->create());

        $component = Livewire::test(BulkSms::class)->set('message', str_repeat('a', 200));

        $this->assertSame(2, $component->instance()->messageUnits());
    }
}
