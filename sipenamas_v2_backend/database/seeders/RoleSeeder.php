<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\Person;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role as SpatieRole;

/**
 * Membuat 6 role modul (mirror App\Enums\Role) di tabel Spatie `roles`,
 * plus role bypass "Super Admin" (lihat AppServiceProvider::boot() Gate::before),
 * dengan guard_name 'sanctum' agar cocok dengan config/auth.php.
 */
class RoleSeeder extends Seeder
{
    public function run(): void
    {
        // Bersihkan cache permission bawaan Spatie
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (Role::cases() as $role) {
            SpatieRole::findOrCreate($role->value, 'sanctum');
        }

        SpatieRole::findOrCreate(Person::SUPER_ADMIN_ROLE, 'sanctum');
    }
}
