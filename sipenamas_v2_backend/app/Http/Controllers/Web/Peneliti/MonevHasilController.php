<?php

namespace App\Http\Controllers\Web\Peneliti;

use App\Enums\JenisPa;
use App\Http\Controllers\Controller;
use App\Http\Requests\Peneliti\SaveMonevJawabanRequest;
use App\Http\Requests\Peneliti\SaveMonevKesimpulanRequest;
use App\Models\Penelitian;
use App\Models\PenelitianMonevHasil;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Padanan pen/myphp/monevhasilpenelitian.php + monevpenelitianhasilreview.php
 * legacy (dan pasangan abdimas-nya monevhasilabdimas.php +
 * monevabdimashasilreview.php): reviewer monev tunjukan Dekan
 * (`penelitian.MONEVHASILBY` = login) mengisi borang `soalmonevpenelitian` /
 * `soalmonevabdimas` ke `penelitian_monevhasil`. Jenis usulan dipilih lewat
 * `?jenis=PENELITIAN|ABDIMAS` (default PENELITIAN).
 */
class MonevHasilController extends Controller
{
    private const HURUF_PILIHAN = ['A', 'B', 'C', 'D', 'E'];

    /**
     * Borang yang sedang dibuka ditandai `?borang={id}`.
     */
    public function index(Request $request): Response
    {
        return Inertia::render('pen/MonevHasilPage', [
            'items' => fn () => $this->daftar($request),
            'borang' => fn () => $request->filled('borang') ? $this->borang($request, $request->integer('borang')) : null,
        ]);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function daftar(Request $request)
    {
        return $this->assignedQuery($request)
            ->with(['skim', 'ketua', 'monevHasil'])
            ->orderBy('TGLMULAI')
            ->get()
            ->map(function (Penelitian $penelitian) {
                $hasil = $penelitian->monevHasil->first();

                return [
                    'id' => $penelitian->id,
                    'judul' => $penelitian->JUDULPENELITIAN,
                    'jenis' => $penelitian->JENIS_PA,
                    'tahun' => $penelitian->PERIODEKEGIATAN_TAHUN,
                    'skim' => $penelitian->skim?->NAMASKIM,
                    'ketua' => $penelitian->ketua?->NAMALENGKAP,
                    'kesimpulan' => $hasil?->KESIMPULAN,
                    'isFinal' => (bool) $hasil?->ISFINAL,
                ];
            });

    }

    /**
     * @return array<string, mixed>
     */
    private function borang(Request $request, int $id): array
    {
        $penelitian = $this->assignedProposal($request, $id);
        $hasil = $this->hasilFor($penelitian);

        $soal = $this->soalQuery($request)->get()->map(fn (Model $soal) => [
            'nomor' => $soal->NOMOR,
            'aspek' => $soal->ASPEKPENILAIAN,
            'pilihan' => $this->pilihanFor($soal),
            'jawaban' => $hasil->{$this->kolomJawaban((int) $soal->NOMOR)},
        ]);

        return [
            'id' => $penelitian->id,
            'judul' => $penelitian->JUDULPENELITIAN,
            'jenis' => $penelitian->JENIS_PA,
            'soal' => $soal,
            'kesimpulan' => $hasil->KESIMPULAN,
            'isFinal' => (bool) $hasil->ISFINAL,
        ];
    }

    public function saveJawaban(SaveMonevJawabanRequest $request, int $id): RedirectResponse
    {
        $hasil = $this->hasilFor($this->assignedProposal($request, $id));

        $hasil->update([$this->kolomJawaban($request->integer('nomor')) => $request->input('jawaban')]);

        return back()->with('success', 'Jawaban disimpan');
    }

    /**
     * Legacy updateFinalkesimpulan: kesimpulan selalu disimpan, tetapi ISFINAL
     * dipaksa 0 selama masih ada jawaban kosong. Soal wajib diambil dari master
     * soal jenis bersangkutan, bukan dipatok 8 — legacy memeriksa JAWAB01..08
     * untuk penelitian tetapi hanya JAWAB01..07 untuk abdimas.
     */
    public function saveKesimpulan(SaveMonevKesimpulanRequest $request, int $id): RedirectResponse
    {
        $hasil = $this->hasilFor($this->assignedProposal($request, $id));

        $lengkap = $this->soalQuery($request)->pluck('NOMOR')
            ->every(fn ($nomor) => filled($hasil->{$this->kolomJawaban((int) $nomor)}));
        $isFinal = $request->boolean('isFinal') && $lengkap;

        $hasil->update([
            'KESIMPULAN' => $request->input('kesimpulan'),
            'ISFINAL' => $isFinal,
        ]);

        // Borang ditutup: kembali ke daftar tanpa `?borang`.
        return redirect('/pen/monev-hasil'.($request->filled('jenis') ? '?jenis='.urlencode($request->string('jenis')) : ''))->with(
            'success',
            $request->boolean('isFinal') && ! $lengkap
                ? 'Kesimpulan disimpan, tetapi belum final karena masih ada jawaban kosong'
                : 'Data sudah disimpan'
        );
    }

    private function assignedProposal(Request $request, int $id): Penelitian
    {
        return $this->assignedQuery($request)->findOrFail($id);
    }

    private function assignedQuery(Request $request): Builder
    {
        return Penelitian::where('JENIS_PA', $this->jenis($request)->value)
            ->whereIn('MONEVHASILBY', $request->user()->kodepersonAliases());
    }

    private function jenis(Request $request): JenisPa
    {
        return JenisPa::fromRequest($request->input('jenis'));
    }

    private function soalQuery(Request $request): Builder
    {
        return $this->jenis($request)->soalMonev()->newQuery()->orderBy('NOMOR');
    }

    private function hasilFor(Penelitian $penelitian): PenelitianMonevHasil
    {
        return $penelitian->monevHasil()->orderBy('id')->firstOrFail();
    }

    private function kolomJawaban(int $nomor): string
    {
        return sprintf('JAWAB%02d', $nomor);
    }

    /**
     * @return list<array{kode: string, label: string}>
     */
    private function pilihanFor(Model $soal): array
    {
        $pilihan = [];

        foreach (self::HURUF_PILIHAN as $index => $huruf) {
            $label = $soal->{sprintf('PIL%02d', $index + 1)};

            if (filled($label)) {
                $pilihan[] = ['kode' => $huruf, 'label' => $label];
            }
        }

        return $pilihan;
    }
}
