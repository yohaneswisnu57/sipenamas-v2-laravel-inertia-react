<?php

namespace Tests\Feature\Middleware;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission as SpatiePermission;
use Spatie\Permission\Models\Role as SpatieRole;
use Tests\TestCase;

/**
 * Exercises the `permission:` middleware alias directly, for the same
 * reason as EnsureRoleTest - real permission-gated routes carry unrelated
 * business-table dependencies that don't belong to this check.
 */
class EnsurePermissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['auth:sanctum', 'permission:view penelitian'])
            ->get('/__test/permission-gated', fn () => response()->json(['ok' => true]));

        SpatiePermission::findOrCreate('view penelitian', 'sanctum');
    }

    public function test_user_without_the_permission_is_denied(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->getJson('/__test/permission-gated')->assertStatus(403);
    }

    public function test_user_with_the_permission_assigned_directly_is_allowed(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('view penelitian');
        Sanctum::actingAs($user);

        $this->getJson('/__test/permission-gated')->assertOk();
    }

    public function test_user_with_the_permission_via_a_role_is_allowed(): void
    {
        $role = SpatieRole::findOrCreate('PEN', 'sanctum');
        $role->givePermissionTo('view penelitian');
        $user = User::factory()->create();
        $user->assignRole('PEN');
        Sanctum::actingAs($user);

        $this->getJson('/__test/permission-gated')->assertOk();
    }

    public function test_super_admin_bypasses_the_permission_check(): void
    {
        SpatieRole::findOrCreate('Super Admin', 'sanctum');
        $user = User::factory()->create();
        $user->assignRole('Super Admin');
        Sanctum::actingAs($user);

        $this->getJson('/__test/permission-gated')->assertOk();
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $this->getJson('/__test/permission-gated')->assertStatus(401);
    }
}
