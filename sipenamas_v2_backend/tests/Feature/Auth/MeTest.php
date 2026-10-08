<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role as SpatieRole;
use Tests\TestCase;

class MeTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_gets_their_profile_with_default_role(): void
    {
        SpatieRole::findOrCreate('PEN', 'sanctum');
        $user = User::factory()->create();
        $user->assignRole('PEN');
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.kodeperson', $user->kodeperson)
            ->assertJsonPath('data.activeRole', 'PEN');
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $this->getJson('/api/v1/auth/me')->assertStatus(401);
    }
}
