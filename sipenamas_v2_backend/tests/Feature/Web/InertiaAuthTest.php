<?php

namespace Tests\Feature\Web;

use App\Http\Controllers\Web\AuthController;
use App\Models\Penelitian;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission as SpatiePermission;
use Spatie\Permission\Models\Role as SpatieRole;
use Tests\TestCase;

class InertiaAuthTest extends TestCase
{
    use RefreshDatabase;

    private function userWithRoles(string ...$roles): User
    {
        $user = User::factory()->external('rahasia')->create();

        foreach ($roles as $role) {
            SpatieRole::findOrCreate($role, 'sanctum');
            $user->assignRole($role);
        }

        return $user;
    }

    public function test_guest_is_redirected_to_login_page(): void
    {
        $this->get('/pen/dashboard')->assertRedirect('/login');

        $this->get('/login')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('auth/LoginPage'));
    }

    public function test_login_starts_session_and_redirects_to_default_dashboard(): void
    {
        $user = $this->userWithRoles('PEN');
        SpatieRole::findByName('PEN', 'sanctum')->givePermissionTo(SpatiePermission::findOrCreate('view penelitian', 'sanctum'));
        $usulan = Penelitian::create(['JUDULPENELITIAN' => 'Usulan Saya', 'PERMOHONANDIBUAT_KDPERSON' => $user->kodeperson]);

        $this->post('/login', ['username' => $user->kodeperson, 'password' => 'rahasia'])
            ->assertRedirect('/pen/dashboard');

        $this->assertAuthenticatedAs($user, 'web');
        $this->assertDatabaseHas('z_log_login', ['USR' => $user->kodeperson]);

        $this->get("/pen/penelitian/{$usulan->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('pen/DetailPenelitianPage')
                ->where('routeParams.id', (string) $usulan->id)
                ->where('proposal.judul', 'Usulan Saya')
                ->where('auth.user.kodeperson', $user->kodeperson)
                ->where('auth.user.activeRole', 'PEN')
                ->where('auth.impersonating', false));
    }

    public function test_wrong_password_returns_error_without_session(): void
    {
        $user = $this->userWithRoles('PEN');

        $this->from('/login')
            ->post('/login', ['username' => $user->kodeperson, 'password' => 'salah'])
            ->assertRedirect('/login')
            ->assertSessionHasErrors(['username' => 'Username atau password salah.']);

        $this->assertGuest('web');
    }

    public function test_page_of_other_module_is_forbidden(): void
    {
        $user = $this->userWithRoles('PEN');

        $this->actingAs($user, 'web')
            ->get('/adm/users')
            ->assertForbidden()
            ->assertInertia(fn (Assert $page) => $page->component('auth/UnauthorizedPage'));
    }

    public function test_role_with_create_permission_opens_the_abdimas_form(): void
    {
        $admin = $this->userWithRoles('ADM');
        SpatieRole::findByName('ADM', 'sanctum')->givePermissionTo(SpatiePermission::findOrCreate('create penelitian', 'sanctum'));

        $this->actingAs($admin, 'web')
            ->get('/pen/abdimas/baru')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('pen/FormUsulanPenelitianPage')
                ->where('isAbdimas', true));
    }

    public function test_switch_role_is_stored_in_session(): void
    {
        $user = $this->userWithRoles('PEN', 'REV');

        $this->actingAs($user, 'web')
            ->post('/switch-role', ['role' => 'REV'])
            ->assertRedirect('/rev/dashboard')
            ->assertSessionHas(AuthController::ACTIVE_ROLE, 'REV');

        $this->get('/rev/dashboard')
            ->assertInertia(fn (Assert $page) => $page->where('auth.user.activeRole', 'REV'));
    }

    public function test_switch_to_role_not_owned_is_rejected(): void
    {
        $user = $this->userWithRoles('PEN');

        $this->actingAs($user, 'web')
            ->from('/pen/dashboard')
            ->post('/switch-role', ['role' => 'ADM'])
            ->assertRedirect('/pen/dashboard')
            ->assertSessionHasErrors('role');
    }

    public function test_admin_logs_in_as_user_and_returns_to_user_management(): void
    {
        $admin = $this->userWithRoles('ADM');
        // Target juga admin, supaya cek "impersonate bertingkat" yang diuji, bukan cek peran actor.
        $target = $this->userWithRoles('PEN', 'ADM');
        $other = $this->userWithRoles('PEN');

        $this->actingAs($admin, 'web')
            ->post("/impersonate/{$target->kodeperson}", ['role' => 'PEN'])
            ->assertRedirect('/pen/dashboard');

        $this->assertAuthenticatedAs($target, 'web');
        $this->assertDatabaseHas('impersonation_logs', [
            'actor_kodeperson' => $admin->kodeperson,
            'target_kodeperson' => $target->kodeperson,
        ]);

        $this->get('/pen/dashboard')
            ->assertInertia(fn (Assert $page) => $page->where('auth.impersonating', true));

        $this->from('/pen/dashboard')
            ->post("/impersonate/{$other->kodeperson}")
            ->assertSessionHasErrors(['impersonate' => 'Tidak bisa impersonate saat sedang impersonate. Kembali dulu ke akun asli.']);

        $this->post('/impersonate/leave')->assertRedirect('/adm/users');
        $this->assertAuthenticatedAs($admin, 'web');
    }

    public function test_non_admin_cannot_log_in_as_other_user(): void
    {
        $actor = $this->userWithRoles('PEN');
        $target = $this->userWithRoles('PEN');

        $this->actingAs($actor, 'web')
            ->from('/pen/dashboard')
            ->post("/impersonate/{$target->kodeperson}")
            ->assertSessionHasErrors('impersonate');

        $this->assertAuthenticatedAs($actor, 'web');
    }

    public function test_logout_ends_session(): void
    {
        $user = $this->userWithRoles('PEN');

        $this->actingAs($user, 'web')->post('/logout')->assertRedirect('/login');

        $this->assertGuest('web');
    }

    public function test_api_accepts_session_of_inertia_pages(): void
    {
        $user = $this->userWithRoles('PEN');

        $this->actingAs($user, 'web')
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.kodeperson', $user->kodeperson);
    }
}
