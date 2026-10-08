<?php

namespace Tests\Feature\Middleware;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role as SpatieRole;
use Tests\TestCase;

/**
 * Exercises the `role:` middleware alias directly through a throwaway
 * route, rather than a real "role:PEN" route (e.g. /api/pen/dashboard) -
 * those pull in unrelated Penelitian/legacy-table queries that are a
 * different feature's concern and would make this test fail for reasons
 * that have nothing to do with role gating.
 */
class EnsureRoleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['auth:sanctum', 'role:PEN'])
            ->get('/__test/role-gated', fn () => response()->json(['ok' => true]));
    }

    public function test_user_without_the_required_role_is_denied(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->getJson('/__test/role-gated')->assertStatus(403);
    }

    public function test_user_with_the_required_role_is_allowed(): void
    {
        SpatieRole::findOrCreate('PEN', 'sanctum');
        $user = User::factory()->create();
        $user->assignRole('PEN');
        Sanctum::actingAs($user);

        $this->getJson('/__test/role-gated')->assertOk();
    }

    public function test_super_admin_bypasses_the_role_check(): void
    {
        SpatieRole::findOrCreate('Super Admin', 'sanctum');
        $user = User::factory()->create();
        $user->assignRole('Super Admin');
        Sanctum::actingAs($user);

        $this->getJson('/__test/role-gated')->assertOk();
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $this->getJson('/__test/role-gated')->assertStatus(401);
    }
}
