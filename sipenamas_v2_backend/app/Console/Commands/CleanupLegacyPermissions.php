<?php

namespace App\Console\Commands;

use App\Models\Person;
use Illuminate\Console\Command;
use Spatie\Permission\Models\Permission as SpatiePermission;
use Spatie\Permission\Models\Role as SpatieRole;

/**
 * Deploy C (cleanup) - HANYA dijalankan setelah routes/api.php sudah cutover
 * ke permission granular baru (Deploy B) dan sudah melewati masa observasi
 * tanpa keluhan akses. Melepas permission generik lama ("PEN.update",
 * "ADM.update") dari semua role dan person, lalu menghapus baris permission
 * itu sendiri. "ADM.read" TIDAK disentuh - masih dipakai nyata, cuma
 * ditambah "ADM.export-sinta" di sampingnya, bukan digantikan.
 */
class CleanupLegacyPermissions extends Command
{
    protected $signature = 'rbac:cleanup-legacy-permissions';

    protected $description = 'Lepas dan hapus permission generik lama (PEN.update, ADM.update) setelah cutover ke permission granular terverifikasi aman';

    private const OBSOLETE = ['PEN.update', 'ADM.update'];

    public function handle(): int
    {
        if (! $this->confirm('Ini akan menghapus permission lama ('.implode(', ', self::OBSOLETE).') dari semua role/person secara permanen. Yakin sudah melewati masa observasi Deploy B?')) {
            $this->warn('Dibatalkan.');

            return self::SUCCESS;
        }

        foreach (self::OBSOLETE as $name) {
            $permission = SpatiePermission::findByName($name, 'sanctum');

            foreach (SpatieRole::all() as $role) {
                $role->revokePermissionTo($permission);
            }

            Person::permission($name)->get()->each(
                fn (Person $person) => $person->revokePermissionTo($permission)
            );

            $permission->delete();

            $this->info("Permission {$name} dilepas dari semua role/person dan dihapus.");
        }

        return self::SUCCESS;
    }
}
