<?php

namespace App\Services\Proposal;

use App\Models\Penelitian;
use Illuminate\Support\Collection;

/**
 * Rekap penilaian semua reviewer satu usulan - padanan
 * adm/myphp/penilaianreviewer.php updateStatuspenilaian.
 *
 * ISBUTUHREVIEWERKETIGA = 1 bila:
 *   (1) ada konflik hasil TOLAK vs LANJUT/PERBAIKAN di antara reviewer, ATAU
 *   (2) selisih skor tertinggi-terendah >= SELISIH_REVIEWER_KETIGA di antara >= 2 reviewer.
 *
 * STATUSPENILAIANREVIEWER direkap setelah semua reviewer aktif FINAL,
 * dengan urutan non-pembanding dulu lalu pembanding (ISREVIEWERPEMBANDING=1)
 * sehingga hasil pembanding menjadi tiebreaker (override PERBAIKAN).
 *
 * Keputusan LOLOS/TIDAK LOLOS tetap di tangan admin - tidak ada penolakan otomatis.
 * Reviewer yang menolak tugas dikecualikan supaya proposal tidak tertahan.
 */
class PenilaianAggregationService
{
    public const SELISIH_REVIEWER_KETIGA = 200;

    public function recompute(Penelitian $penelitian): void
    {
        $reviewers = $penelitian->reviewers()->get()->reject(fn ($r) => $r->isMenolakTugas());

        $sudahMenilai = $reviewers->where('STATUSPENILAIAN', 'FINAL');
        $skor = $sudahMenilai->pluck('TOTALSKOR')->map(fn ($s) => (float) $s);

        // Trigger reviewer ketiga: konflik TOLAK vs LANJUT/PERBAIKAN ATAU selisih besar
        $hasil = $sudahMenilai->pluck('HASILPENILAIAN');
        $adaTolak = $hasil->contains('TOLAK');
        $adaLolosAtauPerbaikan = $hasil->contains(fn ($h) => in_array($h, ['LOLOS', 'LANJUT', 'PERBAIKAN'], true));
        $selisihBesar = $skor->count() >= 2 && ($skor->max() - $skor->min()) >= self::SELISIH_REVIEWER_KETIGA;

        $update = [
            'ISBUTUHREVIEWERKETIGA' => (($adaTolak && $adaLolosAtauPerbaikan) || $selisihBesar) ? 1 : 0,
        ];

        if ($reviewers->isNotEmpty() && $sudahMenilai->count() === $reviewers->count()) {
            $update['SKORAKHIR'] = $skor->isNotEmpty() ? $skor->avg() : 0;
            $update['STATUSPENILAIANREVIEWER'] = $this->hitungStatusPenilaian($sudahMenilai);
        }

        $penelitian->update($update);
    }

    /**
     * Rekap STATUSPENILAIANREVIEWER - padanan loop legacy updateStatuspenilaian.
     *
     * Urutan: non-pembanding (ISREVIEWERPEMBANDING=0) dulu, pembanding terakhir.
     * Pembanding adalah tiebreaker: override status PERBAIKAN dengan hasil mereka.
     */
    private function hitungStatusPenilaian(Collection $sudahMenilai): string
    {
        $statusAkhir = 'LANJUT';
        $jmlPerbaikan = 0;

        // Legacy: ORDER BY ISREVIEWERPEMBANDING ASC, id ASC, ISREVIEWERREVISI ASC
        $ordered = $sudahMenilai->sortBy([
            ['ISREVIEWERPEMBANDING', 'asc'],
            ['id', 'asc'],
            ['ISREVIEWERREVISI', 'asc'],
        ]);

        foreach ($ordered as $r) {
            if ($r->HASILPENILAIAN === 'PERBAIKAN') {
                $jmlPerbaikan++;
            }

            // Legacy reviewer pakai 'LANJUT'; V2 submitPenilaian pakai 'LOLOS' untuk
            // hasil yang sama - keduanya diperlakukan identik di aggregasi.
            if ($r->HASILPENILAIAN === 'TOLAK') {
                $statusAkhir = 'TOLAK';
            } else {
                if ($jmlPerbaikan > 0) {
                    $statusAkhir = 'PERBAIKAN';

                    // Pembanding override PERBAIKAN dengan hasil finalnya (tiebreaker)
                    if ($r->ISREVIEWERPEMBANDING) {
                        $statusAkhir = $r->HASILPENILAIAN;
                    }

                    // Verifikator revisi override setelah verifikasi selesai
                    if ($r->ISREVIEWERREVISI && $r->STATUSPENILAIANREVISI === 'FINAL') {
                        $statusAkhir = $r->REVISI_HASILPENILAIAN === 'SUDAH'
                            ? 'LANJUT (SUDAH PERBAIKAN)'
                            : 'TOLAK (BELUM PERBAIKAN)';
                    }
                } else {
                    $statusAkhir = 'LANJUT';
                }
            }
        }

        return $statusAkhir;
    }
}
