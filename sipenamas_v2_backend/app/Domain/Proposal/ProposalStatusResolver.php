<?php

namespace App\Domain\Proposal;

use App\Enums\ProposalStatus;
use App\Enums\RevisiStatus;
use App\Models\Penelitian;

/**
 * Legacy menyebar status usulan ke belasan kolom flag pada tabel
 * `penelitian` (STATUSFINALAPPROVAL, STATUSPENUNJUKANREVIEWER,
 * STATUSPENILAIANREVIEWER, ISPENGAJUANFINAL, dst) sementara frontend v2
 * mengasumsikan satu enum tunggal (lihat src/utils/constants.js
 * STATUS_USULAN). Kelas ini adalah satu-satunya tempat pemetaan itu
 * dilakukan - jangan baca kolom status legacy langsung dari controller.
 *
 * Urutan aturan di bawah sengaja "first match wins", dari status paling
 * akhir (TUNTAS) ke paling awal (DRAFT).
 *
 * Sudah divalidasi terhadap data produksi nyata (dump `dbsipenamas`,
 * 1290 baris `penelitian`) via `php artisan proposal:resolve-status-report`.
 * Nilai kolom legacy TERNYATA berbeda dari asumsi awal - dicatat di sini
 * supaya tidak diulangi:
 * - STATUSKETUNTASANPENELITIAN juga punya nilai 'TUNTAS BERSYARAT' (177
 *   dari 1290 baris) yang harus ikut dianggap tuntas, bukan cuma 'TUNTAS'
 *   persis. Ada juga 'BATAL' (17 baris) yang TIDAK punya padanan di enum
 *   ProposalStatus frontend saat ini - proposal semacam ini akan jatuh ke
 *   aturan lain di bawahnya (biasanya FINAL_APPROVAL/LOLOS), bukan status
 *   "dibatalkan" yang sebenarnya. Ini gap yang diketahui, butuh keputusan
 *   produk (tambah enum baru di frontend) untuk ditangani dengan benar.
 * - STATUSPENUNJUKANREVIEWER cuma berisi 'DRAFT' atau 'FINAL' di data
 *   nyata - TIDAK PERNAH 'PLOTTED'. 'FINAL' di kolom ini berarti plotting
 *   reviewer sudah selesai/final (terverifikasi lewat JOIN ke
 *   penelitian_reviewer: 980 baris 'FINAL' punya 2013 baris reviewer,
 *   310 baris 'DRAFT' cuma punya 15).
 * - STATUSPENILAIANREVIEWER tidak pernah mengandung substring 'REVISI'.
 *   Nilai nyatanya: '-', 'LANJUT', 'LANJUT (SUDAH PERBAIKAN)', 'PERBAIKAN',
 *   'TOLAK', 'TOLAK (BELUM PERBAIKAN)', 'LOLOS'. 'PERBAIKAN' (tanpa
 *   'SUDAH') dan 'TOLAK (BELUM PERBAIKAN)' berarti masih butuh revisi;
 *   'LANJUT (SUDAH PERBAIKAN)' berarti revisi sudah selesai jadi TIDAK
 *   boleh dianggap REVISI lagi.
 */
class ProposalStatusResolver
{
    /**
     * Nilai STATUSPENILAIANREVIEWER yang berarti "masih menunggu revisi
     * dari peneliti" - lihat catatan validasi data di atas kelas.
     */
    private const STATUS_BUTUH_REVISI = ['PERBAIKAN', 'TOLAK (BELUM PERBAIKAN)'];

    public function resolve(Penelitian $penelitian): ProposalStatus
    {
        if ($this->sudahTuntas($penelitian->STATUSKETUNTASANPENELITIAN)) {
            return ProposalStatus::TUNTAS;
        }

        if (filled($penelitian->FILE_DOKUMENHASILPENELITIAN) || $penelitian->LBRPENGESAHANLAPHASIL_ISFINAL) {
            return ProposalStatus::LAPORAN_AKHIR;
        }

        if ($penelitian->STATUSFINALAPPROVAL === 'LOLOS') {
            return $this->resolvePascaLolos($penelitian);
        }

        if ($penelitian->STATUSFINALAPPROVAL === 'TIDAK LOLOS') {
            return ProposalStatus::TIDAK_LOLOS;
        }

        $reviewers = $penelitian->relationLoaded('reviewers') ? $penelitian->reviewers : $penelitian->reviewers()->get();

        if (in_array($penelitian->STATUSPENILAIANREVIEWER, self::STATUS_BUTUH_REVISI, true)) {
            $revisiAktif = $penelitian->activeRevisiCycle();

            if ($revisiAktif?->status === RevisiStatus::MENUNGGU_VERIFIKASI) {
                return ProposalStatus::MENUNGGU_VERIFIKASI_REVISI;
            }

            return ProposalStatus::REVISI;
        }

        // LANJUT/TOLAK dari PenilaianAggregationService, atau LANJUT (SUDAH PERBAIKAN)
        // dari RevisiCycleService, berarti semua penilaian/verifikasi selesai.
        $statusSiapFinalApproval = ['LANJUT', 'TOLAK', 'LANJUT (SUDAH PERBAIKAN)'];
        $penilaiSelesai = $reviewers->where('ISREVIEWERREVISI', 0)->filter(fn ($r) => ! $r->isMenolakTugas());
        if (in_array($penelitian->STATUSPENILAIANREVIEWER, $statusSiapFinalApproval, true)
            || ($penilaiSelesai->isNotEmpty() && $penilaiSelesai->every(fn ($r) => $r->STATUSPENILAIAN === 'FINAL'))) {
            return ProposalStatus::FINAL_APPROVAL;
        }

        if ($reviewers->contains(fn ($r) => (bool) $r->ISAPPROVED)) {
            return ProposalStatus::REVIEW;
        }

        if ($penelitian->STATUSPENUNJUKANREVIEWER === 'FINAL') {
            return ProposalStatus::PLOTTED;
        }

        if (filled($penelitian->_MSG_PENOLAKANDEKAN) && ! $penelitian->APPROVALPERMOHONAN_ISAPPROVEBYDEKAN) {
            return ProposalStatus::DITOLAK_DEKAN;
        }

        if ($penelitian->ISPENGAJUANFINAL && $penelitian->APPROVALPERMOHONAN_ISAPPROVEBYDEKAN) {
            return ProposalStatus::DISETUJUI_DEKAN;
        }

        if ($penelitian->ISPENGAJUANFINAL) {
            return ProposalStatus::SUBMITTED;
        }

        return ProposalStatus::DRAFT;
    }

    /**
     * Pasca-lolos: LOLOS -> MONEV (ada `penelitian_monevhasil`). Pencairan
     * dana mengikuti Surat Pencairan Dana legacy (CETAKSURATDANA_*), bukan
     * status tersendiri.
     */
    private function resolvePascaLolos(Penelitian $penelitian): ProposalStatus
    {
        $adaMonev = $penelitian->relationLoaded('monevHasil')
            ? $penelitian->monevHasil->isNotEmpty()
            : $penelitian->monevHasil()->exists();

        return $adaMonev ? ProposalStatus::MONEV : ProposalStatus::LOLOS;
    }

    /**
     * 'TUNTAS' persis (692 baris) maupun 'TUNTAS BERSYARAT' (177 baris)
     * dianggap tuntas - keduanya berarti penelitian selesai secara
     * substantif, cuma beda status administratif. 'BELUM TUNTAS' dan '-'
     * sengaja TIDAK match di sini.
     */
    private function sudahTuntas(?string $status): bool
    {
        return in_array($status, ['TUNTAS', 'TUNTAS BERSYARAT'], true);
    }
}
