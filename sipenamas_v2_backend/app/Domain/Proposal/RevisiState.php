<?php

namespace App\Domain\Proposal;

use App\Enums\RevisiStatus;
use App\Models\Penelitian;
use App\Models\PenelitianReviewer;

/**
 * Status siklus revisi yang dibaca dari kolom legacy (tanpa tabel V2):
 *
 * - verifikator  = baris penelitian_reviewer dengan ISREVIEWERREVISI = 1
 *   (legacy adm/penilaianreviewer.php setStatusreviewerrevisi).
 * - MENUNGGU_UPLOAD      : verifikator ada, penelitian.ISDOKUMENPROPOSALREVISIFINAL = 0.
 * - MENUNGGU_VERIFIKASI  : dokumen revisi final, verifikator belum STATUSPENILAIANREVISI = FINAL.
 * - DISETUJUI / DITOLAK  : STATUSPENILAIANREVISI = FINAL dengan REVISI_HASILPENILAIAN SUDAH / BELUM.
 *
 * Tanggapan peneliti per komentar ada di
 * penelitian_penilaianproposal_revisi.KOMENRESPON (lihat KomentarRevisiService),
 * catatan verifikator di penelitian_reviewer.REVISI_KOMENTAR.
 */
final class RevisiState
{
    private function __construct(
        public readonly PenelitianReviewer $verifikator,
        public readonly RevisiStatus $status,
    ) {}

    public static function of(Penelitian $penelitian): ?self
    {
        $reviewers = $penelitian->relationLoaded('reviewers') ? $penelitian->reviewers : $penelitian->reviewers()->get();
        // Ambil baris verifikator terbaru (id terbesar) — satu siklus revisi
        // buat baris baru ISREVIEWERREVISI=1, baris lama (FINAL) tetap ada.
        $verifikator = $reviewers
            ->filter(fn ($r) => (int) $r->ISREVIEWERREVISI === 1)
            ->sortByDesc('id')
            ->first();

        if (! $verifikator) {
            return null;
        }

        $status = match (true) {
            $verifikator->STATUSPENILAIANREVISI === 'FINAL' => $verifikator->REVISI_HASILPENILAIAN === 'SUDAH'
                ? RevisiStatus::DISETUJUI
                : RevisiStatus::DITOLAK,
            (int) $penelitian->ISDOKUMENPROPOSALREVISIFINAL === 1 => RevisiStatus::MENUNGGU_VERIFIKASI,
            default => RevisiStatus::MENUNGGU_UPLOAD,
        };

        return new self($verifikator, $status);
    }
}
