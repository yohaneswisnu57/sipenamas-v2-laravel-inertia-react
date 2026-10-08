<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\Rbac\PermissionCatalog;
use Illuminate\Console\Command;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role as SpatieRole;

/**
 * Sekali jalan (idempotent - aman diulang): pindahkan RBAC dari namespace
 * permission per-modul ("PEN.read", "ADM.manage-users") ke namespace
 * per-resource lintas modul ("view penelitian", "manage user" - lihat
 * PermissionCatalog & tabel rename di plan). ADITIF - permission lama
 * TIDAK dihapus/direvoke di sini, cuma ditambah padanan barunya supaya
 * akses tidak pernah berkurang selama migrasi. Cleanup permission lama
 * jadi command terpisah, dijalankan setelah dipastikan stabil.
 */
class MigrateResourcePermissions extends Command
{
    protected $signature = 'rbac:migrate-resource-permissions';

    protected $description = 'Migrasi permission dari namespace per-modul ke per-resource (aditif, tidak hapus yang lama)';

    /**
     * Permission lama => daftar permission baru yang menggantikannya.
     * Beberapa permission lama pecah jadi banyak (mis. PEN.read dulu
     * menggerbangi 4 resource sekaligus dalam satu middleware).
     *
     * @var array<string, list<string>>
     */
    private const OLD_TO_NEW = [
        'PEN.read' => ['view penelitian', 'view subsidi apc', 'view insentif', 'view hki'],
        'PEN.create' => ['create penelitian', 'create subsidi apc', 'create insentif', 'create hki'],
        'PEN.submit-revisi' => ['submit revisi penelitian'],
        'PEN.submit-monev' => ['submit monev penelitian'],
        'PEN.submit-laporan-akhir' => ['submit laporan akhir penelitian'],
        'ADM.read' => ['view periode', 'view plotting', 'view final approval', 'view user', 'view role', 'view basis data'],
        'ADM.manage-basisdata' => ['manage basis data'],
        'ADM.export-sinta' => ['export sinta'],
        'ADM.create' => ['create periode'],
        'ADM.toggle-periode-aktif' => ['toggle aktif periode'],
        'ADM.assign-reviewer' => ['assign reviewer plotting'],
        'ADM.decide-final-approval' => ['decide final approval penelitian'],
        'ADM.manage-users' => ['manage user'],
        'ADM.manage-roles' => ['manage role'],
        'REV.read' => ['view penelitian'],
        'REV.confirm-kesediaan' => ['confirm kesediaan penugasan'],
        'REV.submit-penilaian' => ['submit penilaian penugasan'],
        'DKN.read' => ['view penelitian'],
        'DKN.approve-proposal' => ['approve penelitian'],
        'DKN.reject-proposal' => ['reject penelitian'],
        'RKT.read' => ['view rektorat'],
        'AKR.read' => ['view akreditasi'],
    ];

    public function handle(): int
    {
        foreach (PermissionCatalog::allPermissionNames() as $name) {
            Permission::findOrCreate($name, 'sanctum');
        }

        $rolesTouched = 0;
        $usersTouched = 0;

        foreach (self::OLD_TO_NEW as $oldName => $newNames) {
            $oldPermission = Permission::where('name', $oldName)->where('guard_name', 'sanctum')->first();

            if (! $oldPermission) {
                continue;
            }

            // Salin label custom (kalau admin pernah ganti lewat Manajemen
            // Menu Permission) ke semua permission baru pengganti - cuma
            // kalau permission baru itu belum punya label sendiri.
            if ($oldPermission->label) {
                Permission::whereIn('name', $newNames)
                    ->where('guard_name', 'sanctum')
                    ->whereNull('label')
                    ->update(['label' => $oldPermission->label]);
            }

            foreach (SpatieRole::whereHas('permissions', fn ($q) => $q->where('permissions.id', $oldPermission->id))->get() as $role) {
                $role->givePermissionTo($newNames);
                $rolesTouched++;
            }

            foreach (User::permission($oldPermission)->get() as $user) {
                $user->givePermissionTo($newNames);
                $usersTouched++;
            }
        }

        $this->info("Selesai. {$rolesTouched} baris role dan {$usersTouched} baris user disentuh (permission lama TIDAK dihapus).");

        return self::SUCCESS;
    }
}
