<?php

namespace App\Console\Commands;

use App\Models\Prodi;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Sekali jalan (bisa diulang, idempotent): cocokkan idhomebase pegawai
 * (uwmsdm.ms_pegawai.idhomebase, read-only) ke prodi lokal via nama unit
 * (uwmsdm.ms_satker.namasatker, WHERE isprogramstudi='1'). Hasil disimpan
 * di homebase_prodi_map, dipakai SyncUsersFromUwmsdm untuk mengisi kolom
 * users.kodeprodi. TIDAK PERNAH menulis apa pun ke koneksi `uwmsdm`.
 */
class MapHomebaseToProdi extends Command
{
    protected $signature = 'rbac:map-homebase-to-prodi';

    protected $description = 'Bangun crosswalk idhomebase (uwmsdm) -> kodeprodi lokal via pencocokan nama unit (read-only ke uwmsdm)';

    public function handle(): int
    {
        $rows = DB::connection('uwmsdm')->select(
            "SELECT DISTINCT mp.idhomebase, ms.namasatker
             FROM ms_pegawai mp
             JOIN ms_satker ms ON mp.idhomebase = ms.idsatker
             WHERE ms.isprogramstudi = '1' AND mp.idhomebase IS NOT NULL"
        );

        $prodiByName = Prodi::all()->keyBy(fn (Prodi $p) => $this->normalize($p->NAMAPRODI));

        $autoMatched = 0;
        $needsManual = 0;

        foreach ($rows as $row) {
            $prodi = $prodiByName->get($this->normalize($row->namasatker));

            DB::table('homebase_prodi_map')->updateOrInsert(
                ['idhomebase' => $row->idhomebase],
                [
                    'kodeprodi' => $prodi?->KODEPRODI,
                    'namasatker_snapshot' => $row->namasatker,
                    'matched_by' => $prodi ? 'auto' : 'manual',
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );

            $prodi ? $autoMatched++ : $needsManual++;
        }

        $this->info("Selesai. {$autoMatched} idhomebase cocok otomatis, {$needsManual} perlu di-mapping manual (kodeprodi masih null di homebase_prodi_map).");

        if ($needsManual > 0) {
            $this->newLine();
            $this->warn('Perlu review manual:');
            foreach (DB::table('homebase_prodi_map')->where('matched_by', 'manual')->get() as $m) {
                $this->line("  {$m->idhomebase} => {$m->namasatker_snapshot}");
            }
        }

        return self::SUCCESS;
    }

    private function normalize(string $name): string
    {
        return strtoupper(trim($name));
    }
}
