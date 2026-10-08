<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;

/**
 * Bentuk output mengikuti mockPeriode di
 * sipenamas_v2_frontend/src/mock/db.js. Field tgl* gelombang diambil dari
 * gelombang aktif pertama (periodegelombang.GELAKTIF = 1) bila ada.
 */
class PeriodeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $gelombangAktif = $this->relationLoaded('gelombang')
            ? $this->gelombang->firstWhere('GELAKTIF', 1)
            : $this->gelombang()->where('GELAKTIF', 1)->first();

        // prodi_anggaran tidak punya relasi Eloquent tersendiri (masih di
        // luar cakupan Fase 1) - agregat pagu universitas dihitung langsung.
        $pagu = DB::table('prodi_anggaran')
            ->where('KDPERIODE', $this->KODEPERIODE)
            ->selectRaw('SUM(ALOKASIANGGARAN + ABDIMAS_ALOKASIANGGARAN) as total_pagu, SUM(JUMLAHPENELITIAN + ABDIMAS_JUMLAHPENELITIAN) as total_kuota')
            ->first();

        return [
            'id' => (string) $this->id,
            'kodeperiode' => $this->KODEPERIODE,
            'tahun' => (string) $this->TAHUN,
            'nama' => $this->DESKRIPSI,
            'isaktif' => (int) $this->ISAKTIF,
            'tglBukaUsulan' => optional($gelombangAktif)->TGLPROPOSAL_FROM,
            'tglTutupUsulan' => optional($gelombangAktif)->TGLPROPOSAL_TO,
            'tglBatasReview' => optional($gelombangAktif)->TGLREVIEW_TO,
            'tglBatasRevisi' => optional($gelombangAktif)->TGLREVISI_TO,
            'tglMonev' => optional($gelombangAktif)->TGLLAPORAN_FROM,
            'tglLaporanAkhir' => optional($gelombangAktif)->TGLLAPORAN_TO,
            'tglPelaksanaanMulai' => $this->TGLPELAKSANAANBEGIN ? substr((string) $this->TGLPELAKSANAANBEGIN, 0, 10) : null,
            'tglPelaksanaanSelesai' => $this->tglPelaksanaanSelesai(),
            'totalPagu' => (float) ($pagu->total_pagu ?? 0),
            'kuotaProposal' => (int) ($pagu->total_kuota ?? 0),
        ];
    }
}
