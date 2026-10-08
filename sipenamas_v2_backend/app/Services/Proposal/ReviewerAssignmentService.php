<?php

namespace App\Services\Proposal;

use App\Models\Penelitian;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Padanan setreviewer.php legacy: menugaskan Reviewer 1 & 2 ke satu usulan.
 *
 * PENTING: kolom STATUSPENUNJUKANREVIEWER di data nyata cuma berisi
 * 'DRAFT' atau 'FINAL' (lihat catatan validasi data di
 * App\Domain\Proposal\ProposalStatusResolver) - dan requirement LPPM
 * memanfaatkan makna asli itu apa adanya: DRAFT = admin memilih reviewer
 * tapi mereka belum diberi tahu/tidak bisa konfirmasi; FINAL = plotting
 * dikunci, reviewer baru muncul di antrian penugasannya
 * (lihat filter STATUSPENUNJUKANREVIEWER='FINAL' di
 * App\Http\Controllers\Api\V1\Reviewer\PenugasanController).
 */
class ReviewerAssignmentService
{
    public function assign(Penelitian $penelitian, string $reviewer1Nik, string $reviewer2Nik): void
    {
        DB::transaction(function () use ($penelitian, $reviewer1Nik, $reviewer2Nik) {
            // Legacy setreviewer.php editData: reviewer yang tetap dipilih
            // tidak dihapus supaya kesediaan & penilaiannya tidak hilang.
            $baru = [$reviewer1Nik, $reviewer2Nik];
            $penelitian->reviewers()->whereNotIn('NIK', $baru)->delete();
            $ada = $penelitian->reviewers()->pluck('NIK')->all();

            foreach (array_diff($baru, $ada) as $nik) {
                $penelitian->reviewers()->create(['NIK' => $nik]);
            }

            $penelitian->update(['STATUSPENUNJUKANREVIEWER' => 'DRAFT']);
        });
    }

    public function finalize(Penelitian $penelitian): void
    {
        if ($penelitian->reviewers()->count() < 2) {
            throw new RuntimeException('Plotting belum lengkap - reviewer 1 dan 2 harus dipilih sebelum finalisasi.');
        }

        $penelitian->update(['STATUSPENUNJUKANREVIEWER' => 'FINAL']);
    }

    /**
     * Reviewer reguler (ISREVIEWERPEMBANDING=0) per proposal maksimal 3
     * (2 awal + 1 tambahan kalau ada yang menolak).
     */
    public const MAKS_REVIEWER = 3;

    /**
     * Tambah reviewer ke-3 reguler (ada yang menolak kesediaan) atau
     * reviewer pembanding (konflik penilaian: TOLAK vs LANJUT/PERBAIKAN
     * atau selisih skor besar).
     *
     * $isPembanding = false → reviewer ke-3 reguler berdampingan dengan
     *   yang menolak (ISREVIEWERPEMBANDING tetap 0). Baris yang menolak
     *   tidak dihapus; riwayat penolakan tetap ada.
     *
     * $isPembanding = true → reviewer pembanding (ISREVIEWERPEMBANDING=1),
     *   hanya bisa ditambahkan bila ISBUTUHREVIEWERKETIGA=1, dan hanya
     *   boleh ada satu per proposal (padanan penilaianreviewerpembanding.php).
     */
    public function tambahReviewerKe3(Penelitian $penelitian, string $reviewerBaruNik, bool $isPembanding = false): void
    {
        if ($isPembanding) {
            if (! $penelitian->ISBUTUHREVIEWERKETIGA) {
                throw new RuntimeException('Reviewer pembanding hanya bisa ditambahkan bila ada konflik penilaian (isButuhReviewerKetiga = true).');
            }

            if ($penelitian->reviewers()->where('ISREVIEWERPEMBANDING', 1)->exists()) {
                throw new RuntimeException('Reviewer pembanding sudah ditunjuk untuk proposal ini.');
            }

            $penelitian->reviewers()->create(['NIK' => $reviewerBaruNik, 'ISREVIEWERPEMBANDING' => 1]);

            return;
        }

        // Reviewer ke-3 reguler - cek batas non-pembanding (null = reguler)
        $nonPembanding = $penelitian->reviewers()->count()
            - $penelitian->reviewers()->where('ISREVIEWERPEMBANDING', 1)->count();
        if ($nonPembanding >= self::MAKS_REVIEWER) {
            throw new RuntimeException('Proposal ini sudah punya maksimal '.self::MAKS_REVIEWER.' reviewer.');
        }

        $penelitian->reviewers()->create(['NIK' => $reviewerBaruNik]);
    }
}
