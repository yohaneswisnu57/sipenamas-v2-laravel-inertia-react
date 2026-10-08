<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission as SpatiePermission;
use Spatie\Permission\Models\Role as SpatieRole;
use Tests\TestCase;

class RoleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        SpatiePermission::findOrCreate('view role', 'sanctum');
        SpatiePermission::findOrCreate('manage role', 'sanctum');
        SpatiePermission::findOrCreate('view penelitian', 'sanctum');
    }

    private function actingAdmin(): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo('view role', 'manage role');
        Sanctum::actingAs($user);

        return $user;
    }

    public function test_updating_permissions_for_super_admin_role_is_rejected(): void
    {
        $this->actingAdmin();

        $response = $this->putJson('/api/v1/adm/roles/'.urlencode(User::SUPER_ADMIN_ROLE).'/permissions', [
            'permissions' => ['view penelitian'],
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Hak akses peran Super Admin tidak dapat diubah.');
    }

    public function test_updating_permissions_for_regular_role_succeeds(): void
    {
        $this->actingAdmin();

        $role = SpatieRole::findOrCreate('custom-reviewer', 'sanctum');

        $response = $this->putJson("/api/v1/adm/roles/{$role->name}/permissions", [
            'permissions' => ['view penelitian'],
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true);

        $this->assertTrue($role->fresh()->hasPermissionTo('view penelitian', 'sanctum'));
    }
}
