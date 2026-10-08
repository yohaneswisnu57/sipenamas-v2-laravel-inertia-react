<?php

namespace App\Services\Proposal;

use App\Models\Penelitian;
use App\Models\PenelitianPenilaianproposal;
use App\Models\PenelitianPenilaianproposalRevisi;
use App\Models\PenelitianReviewer;
use App\Models\Periode;
use App\Models\PeriodeGelombang;
use Illuminate\Support\Collection;

/**
 * Komentar revisi reviewer dan tanggapan peneliti per komentar - tabel
 * legacy `penelitian_penilaianproposal_revisi` (KOMENREVISI / KOMENRESPON),
 * padanan rev|pen/myphp/penilaianproposalrevisi.php dan
 * pen/myphp/hasilreviewpenelitian.php (UPDATERESPON, CEKSTATUSREVIEW).
 */
class KomentarRevisiService
{
    /**
     * Semua komentar revisi usulan, urut waktu, dengan nomor reviewer
     * (identitas reviewer tidak dibuka ke peneliti).
     *
     * @return Collection<int, array{id: int, reviewerKe: int, komentar: string, respon: ?string}>
     */
    public function thread(Penelitian $penelitian): Collection
    {
        $penilaian = PenelitianPenilaianproposal::where('IDPARENT', $penelitian->id)->get(['id', 'IDREVIEWER']);
        $urutanReviewer = $penelitian->reviewers()->orderBy('id')->pluck('id')->flip();

        return PenelitianPenilaianproposalRevisi::whereIn('IDPARENT', $penilaian->pluck('id'))
            ->orderBy('id')
            ->get()
            ->filter(fn ($row) => filled($row->KOMENREVISI))
            ->map(fn ($row) => [
                'id' => $row->id,
                'reviewerKe' => (int) $urutanReviewer->get($penilaian->firstWhere('id', $row->IDPARENT)?->IDREVIEWER, 0) + 1,
                'komentar' => $row->KOMENREVISI,
                'respon' => filled($row->KOMENRESPON) ? $row->KOMENRESPON : null,
            ])
            ->values();
    }

    public function komentar(Penelitian $penelitian, int $komentarId): PenelitianPenilaianproposalRevisi
    {
        return PenelitianPenilaianproposalRevisi::whereIn(
            'IDPARENT',
            PenelitianPenilaianproposal::where('IDPARENT', $penelitian->id)->pluck('id')
        )->findOrFail($komentarId);
    }

    /**
     * Samakan komentar revisi milik satu reviewer dengan daftar baru.
     * Komentar yang teksnya tetap dipertahankan supaya tanggapan peneliti
     * tidak hilang.
     *
     * @param  list<string>  $komentar
     */
    public function sinkron(PenelitianReviewer $penugasan, array $komentar): void
    {
        $penilaian = PenelitianPenilaianproposal::firstOrCreate([
            'IDPARENT' => $penugasan->IDPARENT,
            'IDREVIEWER' => $penugasan->id,
        ]);

        $baru = collect($komentar)->map(fn ($teks) => trim((string) $teks))->filter()->unique()->values();
        $lama = PenelitianPenilaianproposalRevisi::where('IDPARENT', $penilaian->id)->get();

        $lama->reject(fn ($row) => $baru->contains($row->KOMENREVISI))->each->delete();

        $baru->reject(fn ($teks) => $lama->contains('KOMENREVISI', $teks))
            ->each(fn ($teks) => PenelitianPenilaianproposalRevisi::create(['IDPARENT' => $penilaian->id, 'KOMENREVISI' => $teks]));
    }

    /**
     * Padanan CEKSTATUSREVIEW: revisi baru terbuka setelah semua reviewer
     * FINAL dan selama masa revisi gelombang aktif belum lewat.
     */
    public function alasanTertutup(Penelitian $penelitian): ?string
    {
        if ($this->masaRevisiBerakhir()) {
            return 'MASA REVISI SUDAH BERAKHIR.';
        }

        // Penilai aktif: bukan verifikator (ISREVIEWERREVISI=1), bukan yang menolak.
        // Regular reviewer simpan NULL (tidak diset), verifikator simpan 1.
        $reviewers = $penelitian->reviewers()
            ->where(fn ($q) => $q->where('ISREVIEWERREVISI', 0)->orWhereNull('ISREVIEWERREVISI'))
            ->get()
            ->reject(fn ($r) => $r->isMenolakTugas());

        if ($reviewers->isNotEmpty() && $reviewers->contains(fn ($r) => $r->STATUSPENILAIAN !== 'FINAL')) {
            return 'MAAF, PROSES REVIEW BELUM SELESAI.';
        }

        return null;
    }

    /** Batas unggah revisi: `periodegelombang.TGLREVISI_TO` gelombang aktif (Y-m-d), null bila belum diatur. */
    public function batasRevisi(): ?string
    {
        $periode = Periode::where('ISAKTIF', 1)->first();
        $gelombang = $periode ? PeriodeGelombang::where('IDPARENT', $periode->id)->where('GELAKTIF', 1)->first() : null;
        $batas = $gelombang?->TGLREVISI_TO;

        return filled($batas) && ! str_starts_with((string) $batas, '0000') ? substr((string) $batas, 0, 10) : null;
    }

    private function masaRevisiBerakhir(): bool
    {
        $batas = $this->batasRevisi();

        return $batas !== null && $batas < now()->toDateString();
    }
}
