<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Data master (person, penelitian, dst) sudah ada di database
     * legacy `dbsipenamas` - hanya role Spatie (RBAC) yang perlu di-seed.
     */
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            PermissionSeeder::class,
        ]);
    }
}
