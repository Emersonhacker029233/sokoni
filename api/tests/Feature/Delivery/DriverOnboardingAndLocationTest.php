<?php

namespace Tests\Feature\Delivery;

use App\Models\Device;
use App\Models\DriverLocation;
use App\Models\DriverProfile;
use App\Models\User;
use App\Services\Delivery\LogDeliveryNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DriverOnboardingAndLocationTest extends TestCase
{
    use DeliveryTestHelpers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpDelivery();
        Storage::fake('local');
    }

    public function test_full_onboarding_lands_in_pending_review(): void
    {
        $user = User::factory()->create(['phone' => '+255754123456']);

        $this->actingAs($user)->getJson('/api/driver/me')->assertOk()->assertJsonPath('data', null);

        $this->actingAs($user)->postJson('/api/driver/profile', [
            'national_id_number' => '1990-0101-11111-00001-23',
            'licence_number' => 'DL1234567',
            'licence_expires_on' => now()->addYear()->toDateString(),
            'accept_terms' => true,
            'terms_version' => '2026-10',
        ])->assertCreated()->assertJsonPath('data.status', 'pending')->assertJsonPath('data.can_submit', false);

        $this->actingAs($user)->postJson('/api/driver/profile/submit')->assertStatus(422)->assertJsonPath('code', 'profile_incomplete');

        $this->actingAs($user)->post('/api/driver/profile/documents', [
            'photo' => UploadedFile::fake()->image('me.jpg'),
            'national_id_image' => UploadedFile::fake()->image('nida.jpg'),
            'licence_image' => UploadedFile::fake()->create('licence.pdf', 300, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertOk()->assertJsonPath('data.documents.licence_image', true);

        $this->actingAs($user)->putJson('/api/driver/vehicle', ['type' => 'boda_boda', 'plate_number' => 'mc 123 abc', 'colour' => 'Nyekundu'])
            ->assertOk()
            ->assertJsonPath('data.vehicle.plate_number', 'MC123ABC')
            ->assertJsonPath('data.vehicle.verified', false);

        $this->actingAs($user)->postJson('/api/driver/profile/submit')->assertOk()->assertJsonPath('data.status', 'pending');

        $driver = $user->fresh()->driverProfile;
        $this->assertNotNull($driver->submitted_at);
        $this->assertSame('19900101111110000123', $driver->national_id_number);
        Storage::disk('local')->assertExists($driver->national_id_image);
        // Stored encrypted at rest.
        $this->assertStringNotContainsString('19900101111110000123', (string) \DB::table('driver_profiles')->value('national_id_number'));
        // Still cannot work until an admin approves.
        $this->actingAs($user)->getJson('/api/driver/offers')->assertForbidden();
    }

    public function test_signing_up_as_a_driver_needs_a_verified_phone(): void
    {
        $user = User::factory()->create(['phone' => null]);

        $this->actingAs($user)->postJson('/api/driver/profile', [
            'national_id_number' => str_repeat('1', 20), 'accept_terms' => true, 'terms_version' => '2026-10',
        ])->assertStatus(422)->assertJsonPath('code', 'phone_required');
    }

    public function test_plates_are_unique_across_drivers(): void
    {
        $first = $this->driverAt(1);
        $plate = $first->activeVehicle->plate_number;
        $second = DriverProfile::factory()->create();

        $this->actingAs($second->user)->putJson('/api/driver/vehicle', ['type' => 'bajaj', 'plate_number' => $plate])
            ->assertStatus(422)->assertJsonValidationErrors('plate_number');
    }

    public function test_bicycles_need_no_plate_or_licence(): void
    {
        $driver = DriverProfile::factory()->create(['licence_number' => null]);
        $driver->forceFill(['licence_image' => null])->save();

        $this->actingAs($driver->user)->putJson('/api/driver/vehicle', ['type' => 'bicycle'])->assertOk();

        $this->assertSame([], $driver->fresh()->missingRequirements());
    }

    public function test_changing_vehicle_needs_reverification_and_takes_the_driver_offline(): void
    {
        $driver = $this->driverAt(1);

        $this->asDriver($driver)->putJson('/api/driver/vehicle', ['type' => 'car', 'plate_number' => 'T999XYZ'])->assertOk()
            ->assertJsonPath('data.can_go_online', false);

        $this->assertFalse($driver->fresh()->is_online);
        $this->asDriver($driver)->patchJson('/api/driver/availability', ['online' => true, 'lat' => self::SHOP_LAT, 'lng' => self::SHOP_LNG])
            ->assertForbidden()->assertJsonPath('code', 'vehicle_unverified');
    }

    public function test_going_online_and_offline(): void
    {
        $driver = $this->driverAt(1);
        $driver->forceFill(['is_online' => false])->save();

        $this->asDriver($driver)->patchJson('/api/driver/availability', ['online' => true])->assertStatus(422);
        $this->asDriver($driver)->patchJson('/api/driver/availability', ['online' => true, 'lat' => -6.80, 'lng' => 39.28])
            ->assertOk()->assertJsonPath('data.is_online', true);
        $this->assertSame(-6.80, $driver->fresh()->last_lat);

        $this->asDriver($driver)->patchJson('/api/driver/availability', ['online' => false])->assertOk()->assertJsonPath('data.is_online', false);
    }

    public function test_expired_licence_blocks_going_online(): void
    {
        $driver = $this->driverAt(1);
        $driver->forceFill(['licence_expires_on' => now()->subDay()])->save();

        $this->asDriver($driver)->patchJson('/api/driver/availability', ['online' => true, 'lat' => 0, 'lng' => 0])
            ->assertForbidden()->assertJsonPath('code', 'licence_expired');
    }

    public function test_location_is_ignored_while_offline_and_sampled_sparsely_on_a_job(): void
    {
        $driver = $this->driverAt(1);
        $driver->forceFill(['is_online' => false])->save();
        $this->asDriver($driver)->postJson('/api/driver/location', ['lat' => -6.8, 'lng' => 39.28])
            ->assertOk()->assertJsonPath('data.accepted', false);

        $driver->forceFill(['is_online' => true, 'last_located_at' => now()])->save();
        $job = $this->paidJob();
        $this->asDriver($driver)->postJson("/api/driver/offers/{$job->id}/accept")->assertOk();

        $this->asDriver($driver)->postJson('/api/driver/location', ['lat' => -6.81, 'lng' => 39.28])->assertJsonPath('data.sampled', true);
        $this->asDriver($driver)->postJson('/api/driver/location', ['lat' => -6.812, 'lng' => 39.28])->assertJsonPath('data.sampled', false);
        $this->travel(31)->seconds();
        $this->asDriver($driver)->postJson('/api/driver/location', ['lat' => -6.815, 'lng' => 39.28])->assertJsonPath('data.sampled', true);

        $this->assertSame(2, DriverLocation::query()->where('delivery_job_id', $job->id)->count());
        $this->assertSame(-6.815, $driver->fresh()->last_lat);
    }

    public function test_breadcrumbs_older_than_72_hours_are_purged(): void
    {
        $driver = $this->driverAt(1);
        $old = DriverLocation::query()->create(['driver_profile_id' => $driver->id, 'lat' => 0, 'lng' => 0, 'recorded_at' => now()->subHours(73)]);
        $recent = DriverLocation::query()->create(['driver_profile_id' => $driver->id, 'lat' => 0, 'lng' => 0, 'recorded_at' => now()->subHours(71)]);

        $this->artisan('delivery:purge-locations')->assertSuccessful();

        $this->assertModelMissing($old);
        $this->assertModelExists($recent);
    }

    public function test_driver_app_devices_are_kept_apart_from_customer_app_devices(): void
    {
        $user = User::factory()->create();
        Device::query()->create(['user_id' => $user->id, 'fcm_token' => 'customer-token', 'platform' => 'android']);
        $this->actingAs($user)->postJson('/api/driver/devices', ['fcm_token' => 'driver-token', 'platform' => 'android'])->assertOk();

        $probe = new class extends LogDeliveryNotifier
        {
            public array $tokens = [];

            protected function deliver(User $user, Collection $tokens, string $title, string $body, array $data, bool $silent): void
            {
                $this->tokens = $tokens->all();
            }
        };

        $probe->send($user, 'driver', 't', 'b');
        $this->assertSame(['driver-token'], $probe->tokens);

        $probe->send($user, 'sokoni', 't', 'b');
        $this->assertSame(['customer-token'], $probe->tokens);
        // Customer-app pushes also land in the existing in-app inbox; driver ones do not.
        $this->assertSame(1, $user->appNotifications()->count());
    }
}
