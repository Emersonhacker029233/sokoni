<?php

namespace Tests\Feature\Auth;

use Tests\TestCase;

/**
 * Sign in with Apple on Android: Apple form-posts the result to
 * /auth/apple/callback, and the server sends it straight back to the app
 * through the sign_in_with_apple plugin's intent
 * (AppleSignInCallbackController).
 */
class AppleSignInCallbackTest extends TestCase
{
    public function test_apple_result_is_sent_back_to_the_android_app(): void
    {
        $user = json_encode(['name' => ['firstName' => 'Asha', 'lastName' => 'Mushi'], 'email' => 'asha@example.com']);

        $response = $this->post('/auth/apple/callback', [
            'code' => 'c0de.abc',
            'id_token' => 'header.payload.signature',
            'state' => 'Zx9_-q',
            'user' => $user,
        ]);

        $response->assertStatus(307);
        $location = $response->headers->get('Location');

        $this->assertStringStartsWith('intent://callback?', $location);
        $this->assertStringEndsWith('#Intent;package=tz.co.sokoni.sokoni;scheme=signinwithapple;end', $location);

        parse_str(parse_url(explode('#', $location)[0], PHP_URL_QUERY), $query);
        $this->assertSame([
            'code' => 'c0de.abc',
            'id_token' => 'header.payload.signature',
            'state' => 'Zx9_-q',
            'user' => $user,
        ], $query);
    }

    public function test_cancelling_on_apples_page_is_passed_on(): void
    {
        $response = $this->post('/auth/apple/callback', ['error' => 'user_cancelled_authorize', 'state' => 's']);

        $response->assertStatus(307);
        $this->assertSame(
            'intent://callback?state=s&error=user_cancelled_authorize#Intent;package=tz.co.sokoni.sokoni;scheme=signinwithapple;end',
            $response->headers->get('Location'),
        );
    }

    public function test_only_the_fields_the_app_reads_are_forwarded(): void
    {
        $response = $this->post('/auth/apple/callback', [
            'code' => 'c',
            'id_token' => 't',
            'redirect' => 'https://evil.example',
            'state' => ['not', 'a', 'string'],
        ]);

        $this->assertSame(
            'intent://callback?code=c&id_token=t#Intent;package=tz.co.sokoni.sokoni;scheme=signinwithapple;end',
            $response->headers->get('Location'),
        );
    }

    public function test_the_package_can_be_configured(): void
    {
        config(['services.apple.android_package' => 'tz.co.sokoni.other']);

        $this->post('/auth/apple/callback', ['code' => 'c'])
            ->assertRedirect('intent://callback?code=c#Intent;package=tz.co.sokoni.other;scheme=signinwithapple;end');
    }

    public function test_apple_cannot_use_get(): void
    {
        $this->get('/auth/apple/callback')->assertStatus(405);
    }
}
