<?php

namespace Database\Seeders;

use App\Support\Rbac\PermissionCatalog;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission as SpatiePermission;

/**
 * Membuat semua permission per-resource dari
 * PermissionCatalog::ROLE_RESOURCE_PERMISSIONS (guard_name 'sanctum').
 * Aditif - findOrCreate() aman dijalankan berulang, tidak menghapus
 * permission lain. Jalankan ini (`php artisan db:seed --class=PermissionSeeder`)
 * setiap kali menambah permission baru ke katalog, SEBELUM permission itu
 * bisa di-assign ke role manapun lewat Manajemen Role.
 */
class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Bersihkan cache permission bawaan Spatie untuk mencegah "stale cache"
        // setelah ada penambahan/perubahan struktur permission.
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (PermissionCatalog::allPermissionNames() as $name) {
            $permission = SpatiePermission::findOrCreate($name, 'sanctum');

            if (blank($permission->label)) {
                $permission->update(['label' => PermissionCatalog::defaultLabelFor($name)]);
            }
        }
    }
}
