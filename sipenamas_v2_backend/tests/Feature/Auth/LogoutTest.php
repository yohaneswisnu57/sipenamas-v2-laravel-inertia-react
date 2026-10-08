<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LogoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_logout_deletes_the_current_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/auth/logout');

        $response->assertOk()->assertJsonPath('success', true);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_logged_out_token_can_no_longer_authenticate(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")->postJson('/api/v1/auth/logout');

        // The sanctum guard caches its resolved user for the guard object's
        // lifetime, which here spans both simulated requests in this test -
        // without this, the second call would still "see" the deleted
        // token's user instead of re-resolving from the (now empty) table.
        $this->app['auth']->forgetGuards();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/auth/me')
            ->assertStatus(401);
    }
}
