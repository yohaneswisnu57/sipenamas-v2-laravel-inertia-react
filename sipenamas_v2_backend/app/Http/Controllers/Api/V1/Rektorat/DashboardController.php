<?php

namespace App\Http\Controllers\Api\V1\Rektorat;

use App\Http\Controllers\Controller;
use App\Http\Resources\PenelitianResource;
use App\Models\Penelitian;
use App\Support\ApiResponse;

/**
 * Rektorat murni role monitoring/oversight tingkat institusi - tidak ada
 * aksi tulis di sini. Tombol "Setujui Riset Strategis" yang tadinya ada di
 * mock frontend TIDAK diberi endpoint: keputusan final approval sepenuhnya
 * milik Admin/LPPM (App\Http\Controllers\Api\V1\Admin\FinalApprovalController),
 * tidak ada kolom/status "menunggu persetujuan Rektor" terpisah di skema
 * data - membuat endpoint tulis di sini berarti menduplikasi/membajak alur
 * yang sudah ada tanpa dasar bisnis yang jelas.
 *
 * Laporan MBKM (dosenAktifRiset, rasioKeterlibatanMahasiswa, dst) juga
 * TIDAK dibangun - tidak ada tabel/model mahasiswa MBKM di database sama
 * sekali, itu fitur baru yang butuh keputusan produk tersendiri.
 */
class DashboardController extends Controller
{
    public function dashboard()
    {
        $totalPenelitian = Penelitian::count();
        $totalDanaAll = (float) Penelitian::selectRaw('COALESCE(SUM(COALESCE(NOMINALDANA_FINAL, NOMINALDANA)), 0) as total')->value('total');

        $distributionByFaculty = Penelitian::query()
            ->join('prodi', 'prodi.KODEPRODI', '=', 'penelitian.KDPRODI')
            ->join('fakultas', 'fakultas.KODEFAKULTAS', '=', 'prodi.KDFAKULTAS')
            ->selectRaw('fakultas.NAMAFAKULTAS as fakultas, COUNT(*) as jumlah, COALESCE(SUM(COALESCE(penelitian.NOMINALDANA_FINAL, penelitian.NOMINALDANA)), 0) as dana')
            ->groupBy('fakultas.NAMAFAKULTAS')
            ->orderByDesc('jumlah')
            ->get()
            ->map(fn ($row) => [
                'fakultas' => $row->fakultas,
                'jumlah' => (int) $row->jumlah,
                'dana' => (float) $row->dana,
            ]);

        $trendTahunan = Penelitian::query()
            ->selectRaw("TAHUNUSULAN as tahun, COUNT(*) as totalUsulan, SUM(CASE WHEN STATUSFINALAPPROVAL = 'LOLOS' THEN 1 ELSE 0 END) as lolos, COALESCE(SUM(COALESCE(NOMINALDANA_FINAL, NOMINALDANA)), 0) as totalDana")
            ->groupBy('TAHUNUSULAN')
            ->orderBy('TAHUNUSULAN')
            ->get()
            ->map(fn ($row) => [
                'tahun' => (string) $row->tahun,
                'totalUsulan' => (int) $row->totalUsulan,
                'lolos' => (int) $row->lolos,
                'totalDana' => (float) $row->totalDana,
            ]);

        return ApiResponse::success([
            'totalPenelitian' => $totalPenelitian,
            'totalDanaAll' => $totalDanaAll,
            'distributionByFaculty' => $distributionByFaculty,
            'trendTahunan' => $trendTahunan,
        ]);
    }

    /**
     * Riset skim PU (Penelitian Unggulan) atau dana usulan >= 50jt - murni
     * daftar untuk dipantau, bukan antrean keputusan (lihat catatan class).
     */
    public function strategis()
    {
        $proposals = Penelitian::where('KDSKIMPENELITIAN', 'PU')
            ->orWhere('NOMINALDANA', '>=', 50000000)
            ->with(PenelitianResource::EAGER_RELATIONS)
            ->orderByDesc('id')
            ->get();

        return ApiResponse::success(PenelitianResource::collection($proposals));
    }
}
