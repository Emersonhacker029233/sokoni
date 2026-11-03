<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\Drivers\Pages\ListDrivers;
use App\Filament\Resources\Drivers\Pages\ViewDriver;
use App\Models\ActivityLog;
use App\Models\Device;
use App\Models\DriverProfile;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\Sms\SmsGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Feature\Delivery\DeliveryTestHelpers;
use Tests\TestCase;

/** Admin > Trust & Safety > Drivers: the Sokoni Driver review queue (client request 2026-11-03). */
class DriverReviewTest extends TestCase
{
    use DeliveryTestHelpers;
    use RefreshDatabase;

    private RecordingSmsGateway $sms;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpDelivery();
        $this->sms = new RecordingSmsGateway;
        $this->app->instance(SmsGateway::class, $this->sms);
        $this->admin = User::factory()->admin()->create(['name' => 'Asha Admin']);
    }

    /** Filled in all four steps and tapped "Submit for review". */
    private function submittedDriver(array $vehicle = []): DriverProfile
    {
        $driver = DriverProfile::factory()->create();
        $driver->forceFill(['submitted_at' => now()->subHours(3)])->save();
        Vehicle::query()->create([
            'driver_profile_id' => $driver->id,
            'type' => 'boda_boda',
            'plate_number' => 'MC'.fake()->unique()->numerify('###').'ABC',
            'make' => 'TVS',
            'is_active' => true,
            ...$vehicle,
        ]);

        return $driver->fresh();
    }

    /** A fresh browser session per user, like a real separate login. */
    private function as(User $user): static
    {
        $this->flushSession();
        $this->app['auth']->forgetGuards();

        return $this->actingAsAdmin($user);
    }

    private function review(DriverProfile $driver)
    {
        $this->actingAsAdmin($this->admin);

        return Livewire::test(ViewDriver::class, ['record' => $driver->id]);
    }

    public function test_admin_approves_a_driver_and_their_vehicle_and_the_driver_can_go_online(): void
    {
        $driver = $this->submittedDriver();

        $this->review($driver)->callAction('approve')->assertHasNoActionErrors();

        $driver->refresh();
        $this->assertSame('approved', $driver->status);
        $this->assertNotNull($driver->approved_at);
        $this->assertSame($this->admin->id, $driver->approved_by);
        $this->assertNotNull($driver->activeVehicle->verified_at);

        $log = ActivityLog::query()->where('action', 'driver.approved')->sole();
        $this->assertSame($this->admin->id, $log->causer_id);
        $this->assertSame($driver->id, $log->subject_id);
        $this->assertSame(['in_app', 'sms'], $log->meta['notified_by']);

        // In-app record, and SMS because no push can reach the driver app.
        $notification = $driver->user->appNotifications()->sole();
        $this->assertSame(['type' => 'driver_account', 'status' => 'approved'], $notification->data);
        $this->assertCount(1, $this->sms->messages);
        $this->assertSame($driver->user->phone, $this->sms->messages[0]['phone']);

        $this->actingAs($driver->user->fresh())->getJson('/api/driver/me')
            ->assertOk()
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('data.can_go_online', true);
    }

    public function test_approval_is_pushed_instead_of_texted_when_the_driver_app_can_be_reached(): void
    {
        $driver = $this->submittedDriver();
        Device::query()->create(['user_id' => $driver->user_id, 'fcm_token' => 'driver-phone', 'platform' => 'android', 'app' => 'driver']);
        $this->pushes->live = true;

        $this->review($driver)->callAction('approve')->assertHasNoActionErrors();

        $push = $this->pushes->to($driver->user)->sole();
        $this->assertSame('driver', $push['app']);
        $this->assertSame(['type' => 'driver_account', 'status' => 'approved'], $push['data']);
        $this->assertSame([], $this->sms->messages);
        $this->assertSame(['in_app', 'push'], ActivityLog::query()->where('action', 'driver.approved')->sole()->meta['notified_by']);
    }

    public function test_rejecting_requires_a_reason(): void
    {
        $driver = $this->submittedDriver();

        $this->review($driver)
            ->callAction('reject', data: ['reason' => ''])
            ->assertHasActionErrors(['reason' => 'required']);

        $this->assertSame('pending', $driver->fresh()->status);
        $this->assertSame(0, ActivityLog::query()->count());
        $this->assertSame([], $this->sms->messages);
    }

    public function test_the_rejection_reason_is_stored_logged_and_shown_to_the_driver(): void
    {
        $driver = $this->submittedDriver();
        $reason = 'The NIDA card photo is blurred. Retake it in good light.';

        $this->review($driver)->callAction('reject', data: ['reason' => $reason])->assertHasNoActionErrors();

        $driver->refresh();
        $this->assertSame('rejected', $driver->status);
        $this->assertSame($reason, $driver->rejection_reason);
        $this->assertDatabaseHas('activity_logs', [
            'causer_id' => $this->admin->id,
            'action' => 'driver.rejected',
            'subject_id' => $driver->id,
            'reason' => $reason,
        ]);
        $this->assertStringContainsString($reason, $this->sms->messages[0]['body']);

        // What the Sokoni Driver app reads for its "needs changes" screen.
        $this->actingAs($driver->user->fresh())->getJson('/api/driver/me')
            ->assertJsonPath('data.status', 'rejected')
            ->assertJsonPath('data.rejection_reason', $reason)
            ->assertJsonPath('data.can_submit', true);
    }

    public function test_an_incomplete_application_cannot_be_approved(): void
    {
        $driver = DriverProfile::factory()->create(); // no vehicle
        $driver->forceFill(['submitted_at' => now()])->save();

        $this->review($driver)->callAction('approve')->assertNotified('Not done');

        $this->assertSame('pending', $driver->fresh()->status);
        $this->assertFalse(ActivityLog::query()->exists());
    }

    /**
     * Staging 2026-11-04: the screen existed on the server but never showed,
     * because a Filament component cache from before it existed was on the
     * server. This pins what an admin must see once no stale cache is there.
     */
    public function test_drivers_is_discovered_and_hidden_from_staff(): void
    {
        $panel = \Filament\Facades\Filament::getPanel('admin');
        $this->assertContains(\App\Filament\Resources\Drivers\DriverResource::class, $panel->getResources());
        $this->assertFalse($panel->hasCachedComponents(), 'tests must never run against a component cache');

        // Staff first: Filament keeps the built navigation on the panel for
        // the rest of the process, so the first request in a test decides it.
        $staff = User::factory()->staff()->create();
        $this->as($staff)->get('/admin')->assertOk()->assertDontSee('/admin/drivers', false);
    }

    public function test_admins_see_drivers_under_trust_and_safety(): void
    {
        $this->as($this->admin)->get('/admin')->assertOk()
            ->assertSee('Trust &amp; Safety', false)
            ->assertSee('/admin/drivers', false);
    }

    public function test_staff_and_ordinary_users_cannot_open_the_driver_queue(): void
    {
        $driver = $this->submittedDriver();

        foreach ([User::factory()->staff()->create(), User::factory()->create()] as $user) {
            $this->as($user)->get('/admin/drivers')->assertForbidden();
            $this->as($user)->get("/admin/drivers/{$driver->id}")->assertForbidden();
        }

        $this->as($this->admin)->get('/admin/drivers')->assertOk();
        $this->as($this->admin)->get("/admin/drivers/{$driver->id}")->assertOk()->assertSee('NIDA card');
    }

    public function test_documents_are_served_only_to_admins(): void
    {
        Storage::fake('local');
        $driver = $this->submittedDriver();
        Storage::disk('local')->put($driver->national_id_image, 'nida-image-bytes');
        $url = "/admin/driver-documents/{$driver->id}/national-id";

        $this->get($url)->assertRedirect('/admin/login');
        $this->as(User::factory()->create())->get($url)->assertForbidden();
        $this->as(User::factory()->staff()->create())->get($url)->assertForbidden();

        $response = $this->as($this->admin)->get($url);
        $response->assertOk();
        $this->assertSame('nida-image-bytes', $response->streamedContent());
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));

        $this->get($url.'?download=1')
            ->assertHeader('Content-Disposition', 'attachment; filename=driver-'.$driver->id.'-national-id.jpg');
        $this->get("/admin/driver-documents/{$driver->id}/passport")->assertNotFound();

        // The review page links the images through that route, never through storage.
        $this->get("/admin/drivers/{$driver->id}")
            ->assertOk()
            ->assertSee("/admin/driver-documents/{$driver->id}/national-id", false)
            ->assertDontSee('/storage/drivers/', false)
            ->assertSee($driver->national_id_number);
    }

    public function test_documents_are_not_publicly_readable(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $driver = $this->submittedDriver();
        Storage::disk('local')->put($driver->national_id_image, 'nida-image-bytes');
        Storage::disk('local')->put($driver->licence_image, 'licence-bytes');

        // The private disk's file route needs a signature the API never issues for these.
        $this->get('/storage/'.$driver->national_id_image)->assertForbidden();
        $this->get('/storage/'.$driver->licence_image)->assertForbidden();
        $this->assertFalse(Storage::disk('public')->exists($driver->national_id_image));

        // The driver's own API view says only whether each document exists.
        $json = $this->actingAs($driver->user)->getJson('/api/driver/me')->assertOk()->getContent();
        $this->assertStringNotContainsString($driver->national_id_image, $json);
        $this->assertStringNotContainsString($driver->licence_image, $json);
        $this->assertStringNotContainsString($driver->national_id_number, $json);
    }

    public function test_suspend_waits_for_the_current_delivery_then_reinstate_restores_the_driver(): void
    {
        $driver = $this->driverAt(1);
        $job = $this->paidJob();
        $this->asDriver($driver)->postJson("/api/driver/offers/{$job->id}/accept")->assertOk();

        $this->review($driver)->callAction('suspend', data: ['reason' => 'Customer complaint'])->assertNotified('Not done');
        $this->assertSame('approved', $driver->fresh()->status);

        $idle = $this->driverAt(2);
        $this->review($idle)->callAction('suspend', data: ['reason' => 'Customer complaint about rude behaviour'])->assertHasNoActionErrors();
        $idle->refresh();
        $this->assertSame('suspended', $idle->status);
        $this->assertFalse($idle->is_online);
        $this->assertSame('Customer complaint about rude behaviour', $idle->suspended_reason);
        $this->asDriver($idle)->getJson('/api/driver/offers')->assertForbidden()->assertJsonPath('driver_status', 'suspended');

        $this->review($idle)->callAction('reinstate')->assertHasNoActionErrors();
        $this->assertSame('approved', $idle->fresh()->status);
        $this->assertNull($idle->fresh()->suspended_reason);
        $this->assertSame(
            ['driver.suspended', 'driver.reinstated'],
            ActivityLog::query()->orderBy('id')->pluck('action')->all(),
        );
    }

    public function test_admin_unlocks_a_locked_delivery_pin(): void
    {
        $driver = $this->driverAt(1);
        $job = $this->paidJob();
        $this->asDriver($driver)->postJson("/api/driver/offers/{$job->id}/accept")->assertOk();
        $job->forceFill(['pin_attempts' => 5, 'pin_locked_at' => now()])->save();

        $this->review($driver)->callAction('unlockPin')->assertHasNoActionErrors();

        $job->refresh();
        $this->assertNull($job->pin_locked_at);
        $this->assertSame(0, $job->pin_attempts);
        $event = $job->events()->where('type', 'pin_unlocked')->sole();
        $this->assertSame('admin', $event->actor_role);
        $this->assertSame($this->admin->id, $event->actor_user_id);
        $this->assertDatabaseHas('activity_logs', ['action' => 'delivery.pin_unlocked', 'causer_id' => $this->admin->id, 'subject_id' => $job->id]);
        $this->assertTrue($this->pushes->to($driver->user)->contains(fn ($p) => $p['app'] === 'driver'));
    }

    public function test_the_queue_shows_waiting_drivers_oldest_first_and_nobody_else(): void
    {
        $older = $this->submittedDriver();
        $older->forceFill(['submitted_at' => now()->subDays(2)])->save();
        $newer = $this->submittedDriver();
        $newVehicle = $this->driverAt(1);
        $newVehicle->activeVehicle->forceFill(['verified_at' => null])->save();

        $draft = DriverProfile::factory()->create();
        $approved = $this->driverAt(3);
        $rejected = $this->submittedDriver();
        $rejected->forceFill(['status' => 'rejected', 'rejection_reason' => 'x'])->save();

        $this->actingAsAdmin($this->admin);
        Livewire::test(ListDrivers::class)
            ->assertCanSeeTableRecords([$older, $newer], inOrder: true)
            ->assertCanSeeTableRecords([$newVehicle])
            ->assertCanNotSeeTableRecords([$draft, $approved, $rejected]);
    }

    public function test_approving_an_approved_drivers_new_vehicle_keeps_them_approved(): void
    {
        $driver = $this->driverAt(1);
        $driver->activeVehicle->forceFill(['verified_at' => null])->save();

        $this->review($driver)->callAction('approve')->assertHasNoActionErrors();

        $this->assertNotNull($driver->fresh()->activeVehicle->verified_at);
        $this->assertDatabaseHas('activity_logs', ['action' => 'driver.vehicle_verified', 'subject_id' => $driver->id]);
        $this->assertSame('vehicle_approved', $driver->user->appNotifications()->sole()->data['status']);
    }
}
