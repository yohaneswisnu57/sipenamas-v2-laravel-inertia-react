<?php

namespace App\Services\Proposal;

use App\Domain\Proposal\RevisiState;
use App\Enums\RevisiStatus;
use App\Models\Penelitian;
use App\Models\PenelitianReviewer;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Siklus revisi di kolom legacy - lihat App\Domain\Proposal\RevisiState.
 * Admin menunjuk verifikator revisi (orang baru, bukan reviewer 1/2 proposal),
 * peneliti menanggapi komentar lalu mengirim revisi (ISDOKUMENPROPOSALREVISIFINAL), verifikator
 * memutuskan (REVISI_HASILPENILAIAN + STATUSPENILAIANREVISI).
 *
 * Legacy: penilaianreviewerrevisi.php:addData() buat baris baru penelitian_reviewer
 * dengan ISREVIEWERREVISI=1. Verifikator bukan reviewer 1/2 yang sudah menilai.
 */
class RevisiCycleService
{
    /**
     * Tunjuk verifikator revisi: buat baris baru penelitian_reviewer dengan
     * ISREVIEWERREVISI=1. NIK tidak boleh reviewer asli proposal ini
     * (baris dengan STATUSPENILAIANREVISI null = baris plotting/pembanding,
     * bukan verifikator), dan belum pernah menyelesaikan verifikasi revisi
     * proposal yang sama.
     *
     * @throws RuntimeException
     */
    public function tunjukVerifikator(Penelitian $penelitian, string $nik): void
    {
        // Reviewer asli (ISREVIEWERREVISI belum pernah diset) punya STATUSPENILAIANREVISI = null.
        // Baris verifikator (baru atau bekas) selalu diset ke 'DRAFT' atau 'FINAL' saat dibuat.
        $sudahReviewerAsli = $penelitian->reviewers()
            ->where('NIK', $nik)
            ->whereNull('STATUSPENILAIANREVISI')
            ->exists();

        if ($sudahReviewerAsli) {
            throw new RuntimeException('Verifikator revisi tidak boleh reviewer 1/2 yang sudah menilai usulan ini.');
        }

        // Cegah re-assign verifikator yang sudah pernah menyelesaikan verifikasi
        $sudahFinal = $penelitian->reviewers()
            ->where('NIK', $nik)
            ->where('ISREVIEWERREVISI', 1)
            ->where('STATUSPENILAIANREVISI', 'FINAL')
            ->exists();

        if ($sudahFinal) {
            throw new RuntimeException('Reviewer ini sudah pernah menyelesaikan verifikasi revisi usulan ini dan tidak boleh ditunjuk lagi.');
        }

        $statusSebelumnya = RevisiState::of($penelitian)?->status;

        DB::transaction(function () use ($penelitian, $nik, $statusSebelumnya) {
            // Nonaktifkan verifikator sebelumnya (jika ada) yang belum final
            $penelitian->reviewers()
                ->where('ISREVIEWERREVISI', 1)
                ->where('STATUSPENILAIANREVISI', '<>', 'FINAL')
                ->update(['ISREVIEWERREVISI' => 0]);

            // Buat baris baru - sesuai penilaianreviewerrevisi.php:addData() legacy
            $penelitian->reviewers()->create([
                'NIK' => $nik,
                'ISREVIEWERREVISI' => 1,
                'STATUSPENILAIANREVISI' => 'DRAFT',
                'REVISI_HASILPENILAIAN' => '-',
            ]);

            // Siklus baru: peneliti harus upload ulang, kecuali masih MENUNGGU_UPLOAD
            if ($statusSebelumnya !== RevisiStatus::MENUNGGU_UPLOAD) {
                $penelitian->update(['ISDOKUMENPROPOSALREVISIFINAL' => 0]);
            }
        });
    }

    /**
     * Kunci naskah revisi (padanan UPDATEFINAL legacy) dan teruskan ke verifikator.
     *
     * @throws RuntimeException kalau belum ada verifikator yang ditunjuk
     */
    public function finalkan(Penelitian $penelitian): void
    {
        if (RevisiState::of($penelitian)?->status !== RevisiStatus::MENUNGGU_UPLOAD) {
            throw new RuntimeException('Verifikator revisi belum ditunjuk LPPM - hubungi admin LPPM sebelum mengajukan ulang.');
        }

        $penelitian->update(['ISDOKUMENPROPOSALREVISIFINAL' => 1]);
    }

    /**
     * @param  list<string>  $kodepersonAliases  identitas verifikator yang login
     *
     * @throws ModelNotFoundException kalau usulan ini tidak menunggu verifikasi dari verifikator yang login
     */
    public function verify(int $penelitianId, array $kodepersonAliases, RevisiStatus $status, string $catatan): void
    {
        $penelitian = Penelitian::findOrFail($penelitianId);
        $state = RevisiState::of($penelitian);

        if ($state?->status !== RevisiStatus::MENUNGGU_VERIFIKASI || ! in_array($state->verifikator->NIK, $kodepersonAliases, true)) {
            throw (new ModelNotFoundException)->setModel(PenelitianReviewer::class);
        }

        $disetujui = $status === RevisiStatus::DISETUJUI;

        DB::transaction(function () use ($penelitian, $state, $disetujui, $catatan) {
            $state->verifikator->update([
                'REVISI_HASILPENILAIAN' => $disetujui ? 'SUDAH' : 'BELUM',
                'REVISI_KOMENTAR' => $catatan,
                'STATUSPENILAIANREVISI' => 'FINAL',
            ]);

            // Nilai legacy yang sudah bermakna "revisi selesai"/"masih perlu
            // revisi" - lihat ProposalStatusResolver.
            $penelitian->update([
                'STATUSPENILAIANREVIEWER' => $disetujui ? 'LANJUT (SUDAH PERBAIKAN)' : 'TOLAK (BELUM PERBAIKAN)',
            ]);
        });
    }
}
