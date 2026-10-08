<?php

namespace Tests\Feature\Console;

use App\Models\Person;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role as SpatieRole;
use Tests\TestCase;

class MigratePersonRolesToUsersTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Person tidak lagi punya trait HasRoles (lihat catatan pemisahan
     * auth/RBAC di Person.php) - baris model_has_roles bertipe Person yang
     * dimigrasikan command ini adalah peninggalan sebelum pemisahan itu,
     * jadi ditulis langsung ke tabel, bukan lewat assignRole().
     */
    private function attachLegacyPersonRole(Person $person, string $roleName): void
    {
        $role = SpatieRole::findOrCreate($roleName, 'sanctum');

        DB::table('model_has_roles')->insert([
            'role_id' => $role->id,
            'model_type' => Person::class,
            'model_id' => $person->id,
        ]);
    }

    public function test_creates_user_with_local_password_when_never_confirmed_active_in_uwmsdm(): void
    {
        // Person.ISEXTERNAL=false ("pakai SSO"), tapi tidak ada User yang
        // cocok dan tidak pernah disentuh SyncUsersFromUwmsdm - kalau
        // dibiarkan is_external=false, akun ini tidak akan pernah bisa
        // login (SSO pasti menolak orang yang tidak aktif di uwmsdm).
        $person = Person::create([
            'KODEPERSON' => 'P1',
            'NAMALENGKAP' => 'Dosen Purna',
            'EMAIL' => 'purna@ukwms.ac.id',
            'ISEXTERNAL' => false,
            'PASWET' => 'encoded-paswet',
        ]);
        $this->attachLegacyPersonRole($person, 'PEN');

        $this->artisan('rbac:migrate-person-roles-to-users')->assertExitCode(0);

        $user = User::where('kodeperson', 'P1')->first();
        $this->assertNotNull($user);
        $this->assertTrue($user->is_external);
        $this->assertSame('encoded-paswet', $user->paswet);
        $this->assertTrue($user->hasRole('PEN'));
    }

    public function test_does_not_override_is_external_when_user_already_confirmed_active_in_uwmsdm(): void
    {
        User::factory()->create([
            'kodeperson' => 'P2',
            'is_external' => false,
            'synced_at' => now(),
        ]);
        $person = Person::create([
            'KODEPERSON' => 'P2',
            'NAMALENGKAP' => 'Dosen Aktif',
            'ISEXTERNAL' => false,
        ]);
        $this->attachLegacyPersonRole($person, 'PEN');

        $this->artisan('rbac:migrate-person-roles-to-users')->assertExitCode(0);

        $user = User::where('kodeperson', 'P2')->first();
        $this->assertFalse($user->is_external);
    }

    public function test_matches_existing_user_by_email_when_kodeperson_and_nidn_both_changed(): void
    {
        $user = User::factory()->create([
            'kodeperson' => 'P_NEW',
            'nidn' => null,
            'email' => 'staff@ukwms.ac.id',
            'synced_at' => now(),
        ]);
        $person = Person::create([
            'KODEPERSON' => 'P_OLD',
            'NAMALENGKAP' => 'Staff Pindah Unit',
            'EMAIL' => 'staff@ukwms.ac.id',
            'ISEXTERNAL' => false,
        ]);
        $this->attachLegacyPersonRole($person, 'ADM');

        $this->artisan('rbac:migrate-person-roles-to-users')->assertExitCode(0);

        $this->assertDatabaseCount('users', 1);
        $this->assertTrue($user->fresh()->hasRole('ADM'));
        $this->assertDatabaseHas('user_kodeperson_aliases', [
            'kodeperson' => 'P_OLD',
            'user_id' => $user->id,
        ]);
    }
}
