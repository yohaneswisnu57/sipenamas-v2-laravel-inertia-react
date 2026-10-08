<?php

namespace App\Console\Commands;

use App\Models\Person;
use App\Support\Rbac\LegacyPermissionMap;
use Illuminate\Console\Command;
use Spatie\Permission\Models\Role as SpatieRole;

/**
 * Migrasi satu kali (Deploy A, langkah 5 di plan): untuk tiap role/person
 * yang sekarang memegang permission generik lama (mis. "PEN.update"),
 * beri TAMBAHAN permission granular baru padanannya dari LegacyPermissionMap
 * - permission lama TIDAK dilepas di sini (lihat rbac:cleanup-legacy-permissions
 * untuk itu, dijalankan terpisah setelah cutover routes/api.php terverifikasi
 * aman). Idempotent - aman dijalankan ulang (givePermissionTo/assignRole
 * no-op kalau sudah ada).
 */
class MigrateGranularPermissions extends Command
{
    protected $signature = 'rbac:migrate-granular-permissions';

    protected $description = 'Beri permission granular baru (aditif) untuk role/person yang masih pegang permission generik lama, dan backfill role Super Admin dari ISSUPERUSER';

    public function handle(): int
    {
        $superAdminCount = $this->backfillSuperAdmin();
        $roleCount = $this->migrateRolePermissions();
        $personCount = $this->migratePersonPermissions();

        $this->newLine();
        $this->info(sprintf(
            'Selesai. %d person di-backfill role Super Admin, %d role dan %d person disentuh migrasi permission granular.',
            $superAdminCount,
            $roleCount,
            $personCount
        ));

        return self::SUCCESS;
    }

    private function backfillSuperAdmin(): int
    {
        $superUsers = Person::where('ISSUPERUSER', true)->get();

        foreach ($superUsers as $person) {
            $person->assignRole(Person::SUPER_ADMIN_ROLE);
        }

        return $superUsers->count();
    }

    private function migrateRolePermissions(): int
    {
        $touched = 0;

        foreach (SpatieRole::all() as $role) {
            $oldNames = $role->permissions->pluck('name');
            $newNames = $oldNames
                ->flatMap(fn (string $name) => LegacyPermissionMap::newPermissionsFor($name))
                ->unique()
                ->values();

            if ($newNames->isEmpty()) {
                continue;
            }

            $role->givePermissionTo($newNames->all());
            $touched++;
        }

        return $touched;
    }

    private function migratePersonPermissions(): int
    {
        $touched = 0;

        Person::all()->each(function (Person $person) use (&$touched) {
            $oldNames = $person->getDirectPermissions()->pluck('name');
            $newNames = $oldNames
                ->flatMap(fn (string $name) => LegacyPermissionMap::newPermissionsFor($name))
                ->unique()
                ->values();

            if ($newNames->isEmpty()) {
                return;
            }

            $person->givePermissionTo($newNames->all());
            $touched++;
        });

        return $touched;
    }
}
