<?php

namespace App\Http\Controllers\Api\V1\Dekan;

use App\Http\Controllers\Controller;
use App\Models\Penelitian;
use App\Models\Periode;
use App\Models\Prodi;
use App\Models\ProdiAnggaran;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

/**
 * Pagu anggaran penelitian per PRODI, padanan dkn/myphp/anggaranpenelitian.php.
 *
 * Catatan legacy yang ditiru di sini:
 * - Pagu disimpan per prodi + periode (`prodi_anggaran`), BUKAN per fakultas.
 *   Tabel `fakultas_anggaran` yang dipakai `anggaranfakultas.php` tidak ada
 *   di database produksi dan panelnya tidak punya item menu, jadi tidak
 *   diport.
 * - Legacy membuat baris kosong untuk prodi yang belum punya baris periode
 *   tersebut saat daftar dibuka; di sini baris hanya dilengkapi di memori
 *   (nilai 0) supaya endpoint GET tetap read-only.
 * - `editData()` legacy seluruhnya dikomentari, jadi menu ini read-only.
 * - Sisa anggaran legacy dihitung di grid: ALOKASIANGGARAN - proposal
 *   disetujui (tanpa dana LPPM), pengajuan tidak ikut mengurangi.
 */
class AnggaranController extends Controller
{
    public function index(Request $request)
    {
        $periode = $request->filled('kdperiode')
            ? Periode::where('KODEPERIODE', $request->query('kdperiode'))->firstOrFail()
            : Periode::where('ISAKTIF', 1)->firstOrFail();

        $prodiList = Prodi::query()
            ->join('fakultas', 'fakultas.KODEFAKULTAS', '=', 'prodi.KDFAKULTAS')
            ->whereIn('fakultas.KDDEKAN', $request->user()->kodepersonAliases())
            ->orderBy('prodi.KODEPRODI')
            ->get(['prodi.id', 'prodi.KODEPRODI', 'prodi.NAMAPRODI', 'fakultas.NAMAFAKULTAS']);

        $anggaran = ProdiAnggaran::where('KDPERIODE', $periode->KODEPERIODE)
            ->whereIn('IDPARENT', $prodiList->pluck('id'))
            ->get()
            ->keyBy('IDPARENT');

        $realisasi = $this->realisasiPerProdi($prodiList->pluck('KODEPRODI')->all(), $periode->TAHUN);

        $rows = $prodiList->map(function ($prodi) use ($anggaran, $realisasi, $periode) {
            $row = $anggaran->get($prodi->id);
            $angka = $realisasi[$prodi->KODEPRODI] ?? ['pengajuan' => 0.0, 'disetujui' => 0.0, 'danaLppm' => 0.0];
            $alokasi = (float) ($row->ALOKASIANGGARAN ?? 0);

            return [
                'kodeProdi' => $prodi->KODEPRODI,
                'namaProdi' => $prodi->NAMAPRODI,
                'namaFakultas' => $prodi->NAMAFAKULTAS,
                'kdperiode' => $periode->KODEPERIODE,
                'alokasiAnggaran' => $alokasi,
                'anggaranPerPenelitian' => (float) ($row->ANGGARANPERPENELITIAN ?? 0),
                'jumlahPenelitian' => (int) ($row->JUMLAHPENELITIAN ?? 0),
                'isOpenBudget' => (bool) ($row->ISOPENBUDGET ?? false),
                'catatan' => $row->CATATAN ?? null,
                'proposalPengajuan' => $angka['pengajuan'],
                'proposalDisetujui' => $angka['disetujui'],
                'danaLppm' => $angka['danaLppm'],
                'sisaAnggaran' => $alokasi - $angka['disetujui'],
            ];
        });

        return ApiResponse::success([
            'kdperiode' => $periode->KODEPERIODE,
            'tahun' => (int) $periode->TAHUN,
            'totalAlokasi' => (float) $rows->sum('alokasiAnggaran'),
            'totalDisetujui' => (float) $rows->sum('proposalDisetujui'),
            'totalPengajuan' => (float) $rows->sum('proposalPengajuan'),
            'totalSisa' => (float) $rows->sum('sisaAnggaran'),
            'prodi' => $rows->values(),
        ]);
    }

    /**
     * Padanan tiga subquery legacy: TXTPROPPENGAJUAN (semua NOMINALDANA),
     * TXTPROPDISETUJUI (NOMINALDANA_FINAL yang LOLOS dari skim dengan sumber
     * dana non-LPPM) dan TXTDANALPPM (yang sumber dananya dana LPPM).
     *
     * @param  array<int, string>  $kodeProdi
     * @return array<string, array{pengajuan: float, disetujui: float, danaLppm: float}>
     */
    private function realisasiPerProdi(array $kodeProdi, int|string $tahun): array
    {
        if ($kodeProdi === []) {
            return [];
        }

        return Penelitian::query()
            ->leftJoin('skimpenelitian', 'skimpenelitian.KODESKIM', '=', 'penelitian.KDSKIMPENELITIAN')
            ->leftJoin('sumberdana', 'sumberdana.KODESUMBERDANA', '=', 'skimpenelitian.DEFKDSUMBERDANA')
            ->where('penelitian.JENIS_PA', 'PENELITIAN')
            ->where('penelitian.PERIODEKEGIATAN_TAHUN', $tahun)
            ->whereIn('penelitian.KDPRODI', $kodeProdi)
            ->groupBy('penelitian.KDPRODI')
            ->selectRaw('penelitian.KDPRODI as kdprodi')
            ->selectRaw('COALESCE(SUM(penelitian.NOMINALDANA), 0) as pengajuan')
            ->selectRaw("COALESCE(SUM(CASE WHEN penelitian.STATUSFINALAPPROVAL = 'LOLOS' AND COALESCE(sumberdana.ISDANALPPM, 0) <> 1 THEN penelitian.NOMINALDANA_FINAL ELSE 0 END), 0) as disetujui")
            ->selectRaw("COALESCE(SUM(CASE WHEN penelitian.STATUSFINALAPPROVAL = 'LOLOS' AND COALESCE(sumberdana.ISDANALPPM, 0) = 1 THEN penelitian.NOMINALDANA_FINAL ELSE 0 END), 0) as dana_lppm")
            ->get()
            ->mapWithKeys(fn ($row) => [$row->kdprodi => [
                'pengajuan' => (float) $row->pengajuan,
                'disetujui' => (float) $row->disetujui,
                'danaLppm' => (float) $row->dana_lppm,
            ]])
            ->all();
    }
}
