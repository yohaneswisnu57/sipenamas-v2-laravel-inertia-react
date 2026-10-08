<?php

namespace App\Console\Commands;

use App\Domain\Proposal\ProposalStatusResolver;
use App\Models\Penelitian;
use Illuminate\Console\Command;

/**
 * Alat bantu verifikasi ProposalStatusResolver terhadap data produksi
 * nyata (lihat bagian Verifikasi di rencana implementasi Fase 1).
 * Jalankan lalu bandingkan sampelnya dengan tampilan aplikasi legacy
 * sebelum resolver ini dipakai lebih luas di modul Dekan/Reviewer.
 */
class ProposalResolveStatusReport extends Command
{
    protected $signature = 'proposal:resolve-status-report
        {--periode= : Filter KDPERIODE tertentu, default periode aktif}
        {--sample=5 : Jumlah contoh id per status yang ditampilkan}';

    protected $description = 'Tampilkan distribusi status hasil ProposalStatusResolver untuk verifikasi manual terhadap aplikasi legacy';

    public function handle(ProposalStatusResolver $resolver): int
    {
        $query = Penelitian::with(['reviewers', 'monevHasil']);

        $kodePeriode = $this->option('periode');
        if ($kodePeriode) {
            $query->where('KDPERIODE', $kodePeriode);
        } else {
            $query->whereHas('periode', fn ($q) => $q->where('ISAKTIF', 1));
        }

        $proposals = $query->get();

        if ($proposals->isEmpty()) {
            $this->warn('Tidak ada usulan penelitian ditemukan untuk filter ini.');

            return self::SUCCESS;
        }

        $sampleSize = (int) $this->option('sample');
        $grouped = $proposals->groupBy(fn (Penelitian $p) => $resolver->resolve($p)->value);

        $this->table(
            ['Status', 'Jumlah', sprintf('Contoh id (maks %d)', $sampleSize)],
            $grouped->map(fn ($items, $status) => [
                $status,
                $items->count(),
                $items->take($sampleSize)->pluck('id')->implode(', '),
            ])->values()
        );

        $this->newLine();
        $this->info(sprintf('Total %d usulan diproses.', $proposals->count()));

        return self::SUCCESS;
    }
}
