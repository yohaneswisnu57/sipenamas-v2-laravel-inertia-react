<?php

namespace App\Http\Controllers\Api\V1\Rektorat;

use App\Http\Controllers\Controller;
use App\Models\Mahasiswa;
use App\Models\MbkmDatahasilDosen;
use App\Models\MbkmDatahasilMahasiswa;
use App\Models\MbkmDatahasilTendik;
use App\Models\MbkmMhs;
use App\Models\Prodi;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

/**
 * Laporan MBKM, padanan rkt/myphp/mbkm.php, mbkmbyprodi.php, mbkmbelum.php
 * dan mbkmdatahasil{dosen,mahasiswa,tendik}.php.
 *
 * PENTING: modul MBKM legacy adalah **survei berhadiah** (mahasiswa mengisi
 * kuesioner lalu menerima voucher), bukan pendataan keterlibatan mahasiswa
 * dalam penelitian. Tidak ada kolom SKS terkonversi maupun dosen pembimbing
 * di skema legacy, jadi keduanya tidak dibuat di sini.
 *
 * Prodi mahasiswa diturunkan dari NIM lewat `prodi.PREFIXNIK` dengan
 * pencocokan awalan terpanjang, sama seperti subquery legacy.
 */
class MbkmController extends Controller
{
    /** Legacy mbkm.php: daftar mahasiswa yang sudah mengisi. */
    public function pengisian(Request $request)
    {
        $cari = trim((string) $request->query('cari', ''));

        $rows = MbkmMhs::query()
            ->when($cari !== '', fn ($q) => $q->where(fn ($sub) => $sub
                ->where('NIM', 'like', "%{$cari}%")
                ->orWhere('NAMAMAHASISWA', 'like', "%{$cari}%")))
            ->orderByDesc('id')
            ->get();

        $prodiByPrefix = $this->prodiByPrefix();

        return ApiResponse::success($rows->map(fn ($row) => [
            'id' => $row->id,
            'nim' => $row->NIM,
            'nama' => trim((string) $row->NAMAMAHASISWA),
            'namaProdi' => $this->prodiDariNim($row->NIM, $prodiByPrefix),
            'hp' => $row->HP,
            'email' => $row->EMAILNYA,
            'statusSurvey' => $row->SURVEY_STS,
            'tsSurvey' => $row->SURVEY_TIMESTAMP,
            'reward' => [
                'rwd100k' => (float) ($row->RWD100K ?? 0),
                'rwd50k' => (float) ($row->RWD50K ?? 0),
                'rwd20k' => (float) ($row->RWD20K ?? 0),
            ],
            'tsRequest' => $row->TS_REQUEST,
            'tsReward' => $row->TS_REWARD,
        ]));
    }

    /**
     * Legacy mbkmbyprodi.php: rekap jumlah pengisi per prodi beserta
     * persentase terhadap total mahasiswa prodi. Legacy memisahkan kampus
     * Surabaya (`prodi.ISMADIUN = 0`, mahasiswa non-Madiun) dari Madiun;
     * parameter `kampus` di sini mengikuti pemisahan itu.
     */
    public function rekapProdi(Request $request)
    {
        $madiun = $request->query('kampus') === 'MADIUN';

        $prodiList = Prodi::query()
            ->leftJoin('fakultas', 'fakultas.KODEFAKULTAS', '=', 'prodi.KDFAKULTAS')
            ->where('prodi.ISMADIUN', $madiun ? 1 : 0)
            ->orderBy('prodi.KODEPRODI')
            ->get(['prodi.KODEPRODI', 'prodi.NAMAPRODI', 'prodi.PREFIXNIK', 'fakultas.NAMAFAKULTAS']);

        $prodiByPrefix = $this->prodiByPrefix();
        $operator = $madiun ? 'like' : 'not like';

        $pengisiPerProdi = MbkmMhs::query()
            ->join('mahasiswa', 'mahasiswa.NIM', '=', 'mbkm_mhs.NIM')
            ->where('mahasiswa.NAMAPRODI', $operator, '%Madiun%')
            ->pluck('mbkm_mhs.NIM')
            ->countBy(fn ($nim) => $this->prodiDariNim($nim, $prodiByPrefix));

        $totalPerProdi = Mahasiswa::query()
            ->where('NAMAPRODI', $operator, '%Madiun%')
            ->pluck('NIM')
            ->countBy(fn ($nim) => $this->prodiDariNim($nim, $prodiByPrefix));

        $rows = $prodiList->map(function ($prodi) use ($pengisiPerProdi, $totalPerProdi) {
            $jumlah = (int) ($pengisiPerProdi[$prodi->NAMAPRODI] ?? 0);
            $total = (int) ($totalPerProdi[$prodi->NAMAPRODI] ?? 0);

            return [
                'kodeProdi' => $prodi->KODEPRODI,
                'namaProdi' => $prodi->NAMAPRODI,
                'namaFakultas' => $prodi->NAMAFAKULTAS,
                'jumlahPengisi' => $jumlah,
                'totalMahasiswa' => $total,
                'persen' => $total > 0 ? round($jumlah * 100 / $total, 2) : 0.0,
            ];
        });

        return ApiResponse::success([
            'kampus' => $madiun ? 'MADIUN' : 'SURABAYA',
            'totalPengisi' => (int) $rows->sum('jumlahPengisi'),
            'totalMahasiswa' => (int) $rows->sum('totalMahasiswa'),
            'prodi' => $rows->values(),
        ]);
    }

    /** Legacy mbkmbelum.php: mahasiswa yang NIM-nya belum ada di mbkm_mhs. */
    public function belumMengisi(Request $request)
    {
        $cari = trim((string) $request->query('cari', ''));

        $rows = Mahasiswa::query()
            ->whereNotIn('NIM', MbkmMhs::query()->whereNotNull('NIM')->select('NIM'))
            ->when($cari !== '', fn ($q) => $q->where(fn ($sub) => $sub
                ->where('NIM', 'like', "%{$cari}%")
                ->orWhere('NAMAMAHASISWA', 'like', "%{$cari}%")))
            ->orderBy('NIM')
            ->get(['id', 'NIM', 'NAMAMAHASISWA', 'NAMAPRODI', 'STATUSNYA']);

        return ApiResponse::success($rows->map(fn ($row) => [
            'id' => $row->id,
            'nim' => $row->NIM,
            'nama' => trim((string) $row->NAMAMAHASISWA),
            'namaProdi' => $row->NAMAPRODI,
            'status' => $row->STATUSNYA,
        ]));
    }

    /**
     * Legacy mbkmdatahasil{dosen,mahasiswa,tendik}.php: jawaban kuesioner
     * mentah per responden.
     */
    public function dataHasil(Request $request, string $responden)
    {
        $model = match ($responden) {
            'dosen' => MbkmDatahasilDosen::class,
            'mahasiswa' => MbkmDatahasilMahasiswa::class,
            'tendik' => MbkmDatahasilTendik::class,
            default => abort(404, "Responden kuesioner MBKM '{$responden}' tidak dikenal."),
        };

        $cari = trim((string) $request->query('cari', ''));

        $rows = $model::query()
            ->when($cari !== '', fn ($q) => $q->where(fn ($sub) => $sub
                ->where('IDENTITAS', 'like', "%{$cari}%")
                ->orWhere('NAMA', 'like', "%{$cari}%")))
            ->orderBy('id')
            ->get();

        return ApiResponse::success($rows->map(fn ($row) => [
            'id' => $row->id,
            'nomor' => $row->NOMOR,
            'propinsi' => $row->PROPINSI,
            'perguruanTinggi' => $row->PERGURUANTINGGI,
            'programStudi' => $row->PROGRAMSTUDI,
            'identitas' => $row->IDENTITAS,
            'nama' => $row->NAMA,
            'masaKerja' => $row->MASAKERJA,
            'semester' => $row->SEMESTER,
            'pertanyaan' => $row->PERTANYAAN,
            'jawaban' => $row->JAWABAN,
        ]));
    }

    /**
     * Daftar prefix NIM -> nama prodi, diurutkan dari prefix terpanjang
     * supaya pencocokan mengikuti `ORDER BY LENGTH(PREFIXNIK) DESC LIMIT 1`
     * di legacy.
     *
     * @return array<string, string>
     */
    private function prodiByPrefix(): array
    {
        return Prodi::query()
            ->whereNotNull('PREFIXNIK')
            ->where('PREFIXNIK', '<>', '')
            ->orderByRaw('LENGTH(PREFIXNIK) DESC')
            ->pluck('NAMAPRODI', 'PREFIXNIK')
            ->all();
    }

    /** @param  array<string, string>  $prodiByPrefix */
    private function prodiDariNim(?string $nim, array $prodiByPrefix): string
    {
        foreach ($prodiByPrefix as $prefix => $namaProdi) {
            if (str_starts_with((string) $nim, (string) $prefix)) {
                return $namaProdi;
            }
        }

        return '-';
    }
}
