<?php

namespace App\Services\Proposal;

use App\Models\Penelitian;
use App\Models\PenelitianPenilaianproposal;
use App\Models\PenelitianPenilaianproposalDetail;
use App\Models\PenelitianReviewer;
use App\Models\SoalPenilaianProposal;
use App\Models\SoalPenilaianProposalDetail;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Borang penilaian proposal per skim - padanan
 * rev/myphp/penilaianproposaldetail.php (GENLIST, UPDATEROW) dan
 * penilaianproposal.php updateStatusnya. Nilai per kriteria = SKOR x
 * BOBOTPERSEN; total maksimal 700 (skor 7 x bobot 100%).
 *
 * Lihat docs/legacy-flow/penelitian.md §4.
 */
class BorangPenilaianService
{
    /** Pilihan skor di combobox legacy (tanpa 4). */
    public const PILIHAN_SKOR = [1, 2, 3, 5, 6, 7];

    /** Total di bawah ini membuat rekomendasi reviewer otomatis TOLAK. */
    public const SKOR_MINIMUM = 400;

    /**
     * @return Collection<int, SoalPenilaianProposalDetail>
     */
    public function kriteria(Penelitian $penelitian): Collection
    {
        $kodeSoal = $penelitian->skim?->KDSOALPENILAIANPROPOSAL;
        $soal = $kodeSoal ? SoalPenilaianProposal::where('KODESOAL', $kodeSoal)->first() : null;

        return $soal ? $soal->detail()->orderBy('NOMOR')->get() : collect();
    }

    /**
     * @return array<int, array{nomor: int, kriteria: ?string, bobot: int, skor: ?int, nilai: ?int}>
     */
    public function borang(PenelitianReviewer $penugasan): array
    {
        $tersimpan = $this->detailTersimpan($penugasan)->keyBy('NOMORSOAL');

        return $this->kriteria($penugasan->penelitian)->map(fn ($k) => [
            'nomor' => (int) $k->NOMOR,
            'kriteria' => $k->KRITERIAPENILAIAN,
            'bobot' => (int) $k->BOBOTPERSEN,
            'skor' => $tersimpan->get($k->NOMOR)?->SKOR,
            'nilai' => $tersimpan->get($k->NOMOR)?->NILAI,
        ])->values()->all();
    }

    /**
     * Simpan skor kriteria dan kembalikan total nilai. Saat $wajibLengkap
     * false (draf), kriteria yang belum diberi skor boleh kosong - sama
     * dengan legacy yang menyimpan skor per sel ("Progress Jawaban x of n").
     *
     * @param  array<int, array{nomor: int, skor: int}>  $skor
     *
     * @throws ValidationException
     */
    public function simpan(PenelitianReviewer $penugasan, array $skor, bool $wajibLengkap = true): int
    {
        $kriteria = $this->kriteria($penugasan->penelitian)->keyBy(fn ($k) => (int) $k->NOMOR);

        if ($kriteria->isEmpty()) {
            throw ValidationException::withMessages(['skor' => ['Borang penilaian untuk skim ini belum dikonfigurasi.']]);
        }

        $skorPerNomor = collect($skor)->mapWithKeys(fn ($s) => [(int) $s['nomor'] => (int) $s['skor']]);

        if ($skorPerNomor->keys()->diff($kriteria->keys())->isNotEmpty()) {
            throw ValidationException::withMessages(['skor' => ['Nomor kriteria tidak ada di borang skim ini.']]);
        }

        if ($wajibLengkap && $skorPerNomor->count() !== $kriteria->count()) {
            throw ValidationException::withMessages(['skor' => ['Semua kriteria borang wajib diberi skor.']]);
        }

        $kriteria = $kriteria->filter(fn ($k, $nomor) => $skorPerNomor->has($nomor));

        return DB::transaction(function () use ($penugasan, $kriteria, $skorPerNomor) {
            $penilaian = PenelitianPenilaianproposal::firstOrCreate([
                'IDPARENT' => $penugasan->IDPARENT,
                'IDREVIEWER' => $penugasan->id,
            ]);

            PenelitianPenilaianproposalDetail::where('IDPARENT', $penilaian->id)->delete();

            $total = 0;
            foreach ($kriteria as $nomor => $k) {
                $nilai = $skorPerNomor[$nomor] * (int) $k->BOBOTPERSEN;
                $total += $nilai;

                PenelitianPenilaianproposalDetail::create([
                    'IDPARENT' => $penilaian->id,
                    'NOMORSOAL' => $nomor,
                    'SKOR' => $skorPerNomor[$nomor],
                    'NILAI' => $nilai,
                ]);
            }

            return $total;
        });
    }

    /**
     * @return Collection<int, PenelitianPenilaianproposalDetail>
     */
    private function detailTersimpan(PenelitianReviewer $penugasan): Collection
    {
        $penilaianId = PenelitianPenilaianproposal::where('IDPARENT', $penugasan->IDPARENT)
            ->where('IDREVIEWER', $penugasan->id)
            ->value('id');

        return $penilaianId
            ? PenelitianPenilaianproposalDetail::where('IDPARENT', $penilaianId)->get()
            : collect();
    }
}
