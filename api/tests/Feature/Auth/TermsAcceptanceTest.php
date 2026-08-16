<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TermsAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_accept_terms(): void
    {
        $user = User::factory()->create(['terms_accepted_at' => null, 'terms_version' => null]);

        $response = $this->actingAs($user)
            ->postJson('/api/auth/terms/accept', ['version' => '1.0'])
            ->assertOk();

        $response->assertJsonPath('data.terms_accepted', true);

        $user->refresh();
        $this->assertNotNull($user->terms_accepted_at);
        $this->assertSame('1.0', $user->terms_version);
    }

    public function test_accepting_terms_requires_authentication(): void
    {
        $this->postJson('/api/auth/terms/accept', ['version' => '1.0'])
            ->assertStatus(401)
            ->assertJson(['message' => 'Unauthenticated.']);
    }

    public function test_accepting_terms_requires_a_version(): void
    {
        $user = User::factory()->create(['terms_accepted_at' => null, 'terms_version' => null]);

        $this->actingAs($user)
            ->postJson('/api/auth/terms/accept', [])
            ->assertStatus(422);

        $this->assertNull($user->refresh()->terms_accepted_at);
    }

    public function test_re_accepting_terms_updates_the_recorded_version(): void
    {
        $user = User::factory()->create(['terms_accepted_at' => now()->subYear(), 'terms_version' => '1.0']);

        $this->actingAs($user)
            ->postJson('/api/auth/terms/accept', ['version' => '2.0'])
            ->assertOk();

        $this->assertSame('2.0', $user->refresh()->terms_version);
    }
}
