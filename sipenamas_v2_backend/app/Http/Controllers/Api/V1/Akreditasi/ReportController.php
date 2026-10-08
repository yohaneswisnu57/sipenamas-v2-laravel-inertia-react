<?php

namespace App\Http\Controllers\Api\V1\Akreditasi;

use App\Http\Controllers\Controller;
use App\Http\Resources\HkiResource;
use App\Http\Resources\InsentifResource;
use App\Models\Hki;
use App\Models\Insentif;
use App\Models\Penelitian;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

/**
 * Akreditasi murni role read-only/pelaporan - seluruh 3 halaman di
 * frontend (dashboard, data mining borang, rekap luaran) cuma GET/agregasi,
 * tidak ada mutasi data proposal sama sekali. Metrik yang butuh definisi
 * institusional belum jelas (rasio riset/dosen, pemenuhan IKU5) sengaja
 * TIDAK dihitung di sini - hanya angka yang bisa diturunkan langsung dari
 * kolom yang ada.
 *
 * Export ke file .xlsx fisik belum dibangun - belum ada package Excel
 * terinstall (composer.json dicek, tidak ada maatwebsite/laravel-excel dkk).
 * Endpoint di sini mengembalikan data mentah; generate file jadi urusan
 * client (lihat akreditasiApi.js di frontend).
 */
class ReportController extends Controller
{
    public function dashboard()
    {
        $totalPublikasi = Insentif::count();
        $totalPublikasiScopus = Insentif::where('INFOJURNAL_TERINDEKDALAM', 'like', '%scopus%')->count();
        $totalHkiPaten = Hki::count();

        return ApiResponse::success([
            'totalPenelitian' => Penelitian::count(),
            'totalPublikasi' => $totalPublikasi,
            'totalPublikasiScopus' => $totalPublikasiScopus,
            'totalHkiPaten' => $totalHkiPaten,
        ]);
    }

    public function dataMiningBorang(Request $request)
    {
        $query = Penelitian::with(['ketua', 'prodi.fakultas', 'skim', 'sumberDana'])
            ->orderByDesc('id');

        if ($request->filled('prodi')) {
            $prodi = $request->string('prodi')->toString();
            $query->whereHas('prodi', fn ($q) => $q->where('NAMAPRODI', 'like', "%{$prodi}%"));
        }

        if ($request->filled('tahun')) {
            $query->where('TAHUNUSULAN', $request->string('tahun')->toString());
        }

        // Dipakai Tabel 3.b.2 LKPS (riset DTPS yang melibatkan mahasiswa).
        if ($request->boolean('denganMahasiswa')) {
            $query->whereHas('mahasiswa');
        }

        $rows = $query->get()->map(fn (Penelitian $p) => [
            'tahunAkademik' => (string) $p->TAHUNUSULAN,
            'namaDosen' => $p->ketua?->NAMALENGKAP,
            'nidn' => $p->ketua?->NIDN ?: $p->PERMOHONANDIBUAT_KDPERSON,
            'prodi' => $p->prodi?->NAMAPRODI,
            'fakultas' => $p->prodi?->fakultas?->NAMAFAKULTAS,
            'judulPenelitian' => $p->JUDULPENELITIAN,
            'skema' => $p->skim?->NAMASKIM,
            'sumberDana' => $p->sumberDana?->NAMASUMBERDANA,
            'jumlahDana' => (float) ($p->NOMINALDANA_FINAL ?? $p->NOMINALDANA),
            'luaranArtikel' => $p->TARGETLUARAN,
        ]);

        return ApiResponse::success($rows->values());
    }

    public function rekapLuaran()
    {
        $jurnal = Insentif::with('pengaju.fakultas', 'indexJurnal')->orderByDesc('id')->get();
        $hki = Hki::with('peserta.person.fakultas')->orderByDesc('id')->get();

        return ApiResponse::success([
            'jurnal' => InsentifResource::collection($jurnal),
            'hki' => HkiResource::collection($hki),
        ]);
    }
}
