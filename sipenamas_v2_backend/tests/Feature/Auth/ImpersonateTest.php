<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role as SpatieRole;
use Tests\TestCase;

class ImpersonateTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_impersonate_a_user_with_a_role(): void
    {
        SpatieRole::findOrCreate('Super Admin', 'sanctum');
        SpatieRole::findOrCreate('PEN', 'sanctum');
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');
        $target = User::factory()->create();
        $target->assignRole('PEN');
        Sanctum::actingAs($admin, ['*']);

        $response = $this->postJson("/api/v1/auth/impersonate/{$target->kodeperson}");

        $response->assertOk()->assertJsonPath('data.user.kodeperson', $target->kodeperson);
        $this->assertDatabaseHas('impersonation_logs', [
            'actor_kodeperson' => $admin->kodeperson,
            'target_kodeperson' => $target->kodeperson,
            'role' => 'PEN',
        ]);
    }

    public function test_lppm_admin_can_impersonate_a_user_with_a_role(): void
    {
        SpatieRole::findOrCreate('ADM', 'sanctum');
        SpatieRole::findOrCreate('PEN', 'sanctum');
        $admin = User::factory()->create();
        $admin->assignRole('ADM');
        $target = User::factory()->create();
        $target->assignRole('PEN');
        Sanctum::actingAs($admin, ['*']);

        $response = $this->postJson("/api/v1/auth/impersonate/{$target->kodeperson}");

        $response->assertOk()->assertJsonPath('data.user.kodeperson', $target->kodeperson);
        $this->assertDatabaseHas('impersonation_logs', [
            'actor_kodeperson' => $admin->kodeperson,
            'target_kodeperson' => $target->kodeperson,
            'role' => 'PEN',
        ]);
    }

    public function test_non_super_admin_cannot_impersonate(): void
    {
        $actor = User::factory()->create();
        $target = User::factory()->create();
        Sanctum::actingAs($actor, ['*']);

        $this->postJson("/api/v1/auth/impersonate/{$target->kodeperson}")
            ->assertStatus(403);
    }

    public function test_cannot_impersonate_while_already_impersonating(): void
    {
        SpatieRole::findOrCreate('Super Admin', 'sanctum');
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');
        $target = User::factory()->create();

        // The controller reads the token's raw `abilities` array (not
        // tokenCan()) specifically so '*' tokens aren't mistaken for an
        // impersonation token - Sanctum::actingAs() only stubs can() on a
        // mock and never populates that attribute, so a real token is
        // required to exercise this check at all.
        $token = $admin->createToken('impersonation', ['impersonation'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/auth/impersonate/{$target->kodeperson}")
            ->assertStatus(403);
    }

    public function test_cannot_impersonate_self(): void
    {
        SpatieRole::findOrCreate('Super Admin', 'sanctum');
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');
        Sanctum::actingAs($admin, ['*']);

        $this->postJson("/api/v1/auth/impersonate/{$admin->kodeperson}")
            ->assertStatus(422);
    }

    public function test_impersonating_a_nonexistent_user_and_a_roleless_user_return_the_same_response(): void
    {
        SpatieRole::findOrCreate('Super Admin', 'sanctum');
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');
        $roleless = User::factory()->create();
        Sanctum::actingAs($admin, ['*']);

        $nonexistent = $this->postJson('/api/v1/auth/impersonate/DOES-NOT-EXIST');
        $withoutRole = $this->postJson("/api/v1/auth/impersonate/{$roleless->kodeperson}");

        $nonexistent->assertStatus(422);
        $withoutRole->assertStatus(422);
        $this->assertSame($nonexistent->json('message'), $withoutRole->json('message'));
    }
}
