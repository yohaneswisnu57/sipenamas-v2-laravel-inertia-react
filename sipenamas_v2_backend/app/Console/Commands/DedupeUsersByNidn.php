<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Sekali jalan (idempotent - aman diulang): cari User yang kepecah jadi
 * lebih dari satu baris untuk NIDN yang sama (gejala NIP berubah - lihat
 * MigratePersonRolesToUsers). Untuk tiap grup, gabungkan role/permission
 * ke baris dengan `synced_at` paling baru (paling representatif sebagai
 * identitas aktif), catat kodeperson baris lain sebagai alias, lalu
 * lucuti role dari baris duplikat (TIDAK dihapus - dibiarkan sebagai
 * arsip supaya token/riwayat lain yang mungkin nempel tidak hilang).
 */
class DedupeUsersByNidn extends Command
{
    protected $signature = 'rbac:dedupe-users-by-nidn';

    protected $description = 'Gabungkan User yang kepecah jadi beberapa baris untuk NIDN yang sama (akibat NIP berubah)';

    public function handle(): int
    {
        $dupNidns = User::whereNotNull('nidn')->where('nidn', '!=', '')
            ->select('nidn')
            ->groupBy('nidn')
            ->havingRaw('count(*) > 1')
            ->pluck('nidn');

        if ($dupNidns->isEmpty()) {
            $this->info('Tidak ada duplikat NIDN. Aman.');

            return self::SUCCESS;
        }

        $this->info("Ditemukan {$dupNidns->count()} NIDN dengan lebih dari satu User.");

        foreach ($dupNidns as $nidn) {
            $group = User::where('nidn', $nidn)->orderByDesc('synced_at')->get();
            $primary = $group->first();
            $duplicates = $group->slice(1);

            foreach ($duplicates as $dup) {
                $primary->syncRoles(array_unique([
                    ...$primary->getRoleNames()->all(),
                    ...$dup->getRoleNames()->all(),
                ]));
                $primary->syncPermissions(array_unique([
                    ...$primary->getPermissionNames()->all(),
                    ...$dup->getPermissionNames()->all(),
                ]));

                DB::table('user_kodeperson_aliases')->updateOrInsert(
                    ['kodeperson' => $dup->kodeperson],
                    ['user_id' => $primary->id]
                );

                $dup->syncRoles([]);
                $dup->syncPermissions([]);
                $dup->update(['nidn' => null]);

                $this->line("  NIDN {$nidn}: {$dup->kodeperson} digabung ke {$primary->kodeperson}");
            }
        }

        $this->info('Selesai.');

        return self::SUCCESS;
    }
}
