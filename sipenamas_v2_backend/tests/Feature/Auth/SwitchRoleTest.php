<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role as SpatieRole;
use Tests\TestCase;

class SwitchRoleTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_switch_to_a_role_they_are_allowed(): void
    {
        SpatieRole::findOrCreate('PEN', 'sanctum');
        SpatieRole::findOrCreate('ADM', 'sanctum');
        $user = User::factory()->create();
        $user->assignRole(['PEN', 'ADM']);
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/auth/switch-role', ['role' => 'PEN'])
            ->assertOk()
            ->assertJsonPath('data.activeRole', 'PEN');
    }

    public function test_user_cannot_switch_to_a_role_they_are_not_allowed(): void
    {
        SpatieRole::findOrCreate('PEN', 'sanctum');
        SpatieRole::findOrCreate('ADM', 'sanctum');
        $user = User::factory()->create();
        $user->assignRole('PEN');
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/auth/switch-role', ['role' => 'ADM'])
            ->assertStatus(403)
            ->assertJsonPath('success', false);
    }

    public function test_switch_role_rejects_an_unknown_role_value(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/auth/switch-role', ['role' => 'NOPE'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['role']);
    }
}
