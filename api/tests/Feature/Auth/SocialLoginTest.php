<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SocialLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_google_token_creates_a_user_and_issues_a_token(): void
    {
        config(['services.google.client_id' => 'sokoni-client-id']);

        Http::fake([
            'oauth2.googleapis.com/*' => Http::response([
                'sub' => 'google-uid-123',
                'aud' => 'sokoni-client-id',
                'email' => 'amina@example.com',
                'name' => 'Amina',
                'picture' => 'https://example.com/avatar.jpg',
            ]),
        ]);

        $response = $this->postJson('/api/auth/social', [
            'provider' => 'google',
            'token' => 'fake-id-token',
        ])->assertOk();

        $response->assertJsonPath('user.name', 'Amina');
        $this->assertDatabaseHas('users', ['email' => 'amina@example.com', 'provider_id' => 'google-uid-123']);
    }

    public function test_token_with_wrong_audience_is_rejected(): void
    {
        config(['services.google.client_id' => 'sokoni-client-id']);

        Http::fake([
            'oauth2.googleapis.com/*' => Http::response([
                'sub' => 'google-uid-123',
                'aud' => 'some-other-app',
                'email' => 'amina@example.com',
            ]),
        ]);

        $this->postJson('/api/auth/social', [
            'provider' => 'google',
            'token' => 'fake-id-token',
        ])->assertStatus(422);
    }

    public function test_signing_in_twice_with_the_same_identity_reuses_the_same_user(): void
    {
        config(['services.google.client_id' => 'sokoni-client-id']);

        Http::fake([
            'oauth2.googleapis.com/*' => Http::response([
                'sub' => 'google-uid-123',
                'aud' => 'sokoni-client-id',
                'email' => 'amina@example.com',
                'name' => 'Amina',
            ]),
        ]);

        $this->postJson('/api/auth/social', ['provider' => 'google', 'token' => 'a'])->assertOk();
        $this->postJson('/api/auth/social', ['provider' => 'google', 'token' => 'b'])->assertOk();

        $this->assertSame(1, \App\Models\User::query()->where('email', 'amina@example.com')->count());
    }
}
