<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\VerifyEmailNotification;
use App\Support\SafeMail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * C5: optional email field + verification link flow. Order/review email
 * notifications have their own test file (OrderAndReviewEmailNotificationsTest).
 */
class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registering_with_an_email_sends_a_verification_link(): void
    {
        Notification::fake();
        $phone = '+255754123456';
        $this->postJson('/api/auth/otp/request', ['phone' => $phone]);
        $code = Cache::get('otp:'.$phone);

        $this->postJson('/api/auth/register', [
            'phone' => $phone,
            'code' => $code,
            'name' => 'Amina Buyer',
            'email' => 'amina@example.com',
            'account_intent' => 'buy',
            'terms_version' => '1.0',
        ])->assertOk();

        $user = User::where('phone', $phone)->firstOrFail();
        $this->assertSame('amina@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
        Notification::assertSentTo($user, VerifyEmailNotification::class);
    }

    public function test_registering_without_an_email_sends_no_verification_link(): void
    {
        Notification::fake();
        $phone = '+255754123456';
        $this->postJson('/api/auth/otp/request', ['phone' => $phone]);
        $code = Cache::get('otp:'.$phone);

        $this->postJson('/api/auth/register', [
            'phone' => $phone,
            'code' => $code,
            'name' => 'Amina Buyer',
            'account_intent' => 'buy',
            'terms_version' => '1.0',
        ])->assertOk();

        Notification::assertNothingSent();
    }

    public function test_a_duplicate_email_is_rejected_at_registration(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);
        $phone = '+255754123456';
        $this->postJson('/api/auth/otp/request', ['phone' => $phone]);
        $code = Cache::get('otp:'.$phone);

        $this->postJson('/api/auth/register', [
            'phone' => $phone,
            'code' => $code,
            'name' => 'Amina Buyer',
            'email' => 'taken@example.com',
            'account_intent' => 'buy',
            'terms_version' => '1.0',
        ])->assertStatus(422);
    }

    public function test_the_signed_link_verifies_the_email(): void
    {
        $user = User::factory()->create(['email' => 'amina@example.com', 'email_verified_at' => null]);
        $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
            'id' => $user->id,
            'hash' => sha1($user->email),
        ]);

        $response = $this->get($url);

        $response->assertRedirect(route('web.home'));
        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_a_verification_link_works_even_if_the_clicking_browser_is_not_signed_in(): void
    {
        $user = User::factory()->create(['email' => 'amina@example.com', 'email_verified_at' => null]);
        $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
            'id' => $user->id,
            'hash' => sha1($user->email),
        ]);

        // Deliberately no actingAs() — the whole point of this route (see
        // VerifyEmailController's docblock) is that it doesn't require one.
        $this->get($url)->assertRedirect(route('web.home'));
        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_a_tampered_hash_is_rejected(): void
    {
        $user = User::factory()->create(['email' => 'amina@example.com', 'email_verified_at' => null]);
        $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
            'id' => $user->id,
            'hash' => sha1('someone-else@example.com'),
        ]);

        $this->get($url)->assertForbidden();
        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_an_expired_link_is_rejected(): void
    {
        $user = User::factory()->create(['email' => 'amina@example.com', 'email_verified_at' => null]);
        $url = URL::temporarySignedRoute('verification.verify', now()->subMinutes(1), [
            'id' => $user->id,
            'hash' => sha1($user->email),
        ]);

        $this->get($url)->assertForbidden();
    }

    public function test_changing_email_via_web_settings_resets_verification_and_resends(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'old@example.com', 'email_verified_at' => now(), 'account_intent' => 'buy']);

        $this->actingAsWebUser($user)->post(route('web.account.settings.update'), [
            'name' => $user->name,
            'email' => 'new@example.com',
            'locale' => 'en',
        ])->assertRedirect();

        $user->refresh();
        $this->assertSame('new@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
        Notification::assertSentTo($user, VerifyEmailNotification::class);
    }

    public function test_clearing_email_via_web_settings_is_allowed_and_sends_nothing(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'old@example.com', 'email_verified_at' => now(), 'account_intent' => 'buy']);

        $this->actingAsWebUser($user)->post(route('web.account.settings.update'), [
            'name' => $user->name,
            'email' => '',
            'locale' => 'en',
        ])->assertRedirect();

        $user->refresh();
        $this->assertNull($user->email);
        $this->assertNull($user->email_verified_at);
        Notification::assertNothingSent();
    }

    public function test_saving_settings_without_changing_email_does_not_reset_verification(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'same@example.com', 'email_verified_at' => now(), 'account_intent' => 'buy']);
        $verifiedAt = $user->email_verified_at;

        $this->actingAsWebUser($user)->post(route('web.account.settings.update'), [
            'name' => 'New Name',
            'email' => 'same@example.com',
            'locale' => 'en',
        ])->assertRedirect();

        $user->refresh();
        $this->assertSame('New Name', $user->name);
        $this->assertEquals($verifiedAt, $user->email_verified_at);
        Notification::assertNothingSent();
    }

    public function test_the_api_profile_endpoint_applies_the_same_email_change_handling(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'old@example.com', 'email_verified_at' => now()]);

        $this->actingAs($user)->patchJson('/api/auth/profile', [
            'name' => $user->name,
            'email' => 'new@example.com',
        ])->assertOk();

        $user->refresh();
        $this->assertSame('new@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
        Notification::assertSentTo($user, VerifyEmailNotification::class);
    }

    public function test_the_api_profile_endpoint_rejects_an_email_already_taken_by_someone_else(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);
        $user = User::factory()->create(['email' => null]);

        $this->actingAs($user)->patchJson('/api/auth/profile', [
            'name' => $user->name,
            'email' => 'taken@example.com',
        ])->assertStatus(422);
    }

    public function test_resend_does_nothing_for_an_already_verified_email(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'amina@example.com', 'email_verified_at' => now(), 'account_intent' => 'buy']);

        $this->actingAsWebUser($user)->post(route('web.account.settings.resend-verification'))->assertRedirect();

        Notification::assertNothingSent();
    }

    public function test_resend_sends_again_for_an_unverified_email(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'amina@example.com', 'email_verified_at' => null, 'account_intent' => 'buy']);

        $this->actingAsWebUser($user)->post(route('web.account.settings.resend-verification'))->assertRedirect();

        Notification::assertSentTo($user, VerifyEmailNotification::class);
    }

    /** C5's core resilience guarantee: a mail failure must never fail the user's action. */
    public function test_a_notification_send_failure_is_caught_and_logged_not_thrown(): void
    {
        $user = new class extends User
        {
            public function notify($instance): void
            {
                throw new \RuntimeException('SMTP connection refused');
            }
        };

        SafeMail::send($user, new VerifyEmailNotification);

        $this->assertTrue(true); // Reaching here means the exception was swallowed.
    }
}
