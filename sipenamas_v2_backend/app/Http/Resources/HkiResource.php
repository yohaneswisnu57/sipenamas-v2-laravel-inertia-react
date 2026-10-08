<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Bentuk output mengikuti mockHkiList. Kepemilikan (personId/namaPengusul)
 * diambil dari peserta pertama di `hki_peserta`, bukan kolom langsung -
 * lihat App\Models\Hki::scopeForPeneliti().
 */
class HkiResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $peserta = $this->relationLoaded('peserta') ? $this->peserta : $this->peserta()->get();
        $utama = $peserta->first();

        return [
            'id' => (string) $this->id,
            'personId' => $utama?->NIP,
            'namaPengusul' => $utama?->person?->NAMALENGKAP ?: $utama?->NAMA,
            'fakultas' => $utama?->person?->fakultas?->NAMAFAKULTAS,
            'jenisCiptaan' => $this->JENISHKI,
            'judulCiptaan' => $this->JUDUL,
            'nomorPencatatan' => $this->NOPENDAFTARAN ?: ($this->NOSK ?: $this->NOPATEN),
            'status' => $this->STATUSHKI ?: 'PEMERIKSAAN_SUBSTANTIF',
            'tglDaftar' => optional($this->TGLDAFTAR)->format('Y-m-d'),
            'tglTerbit' => optional($this->TGLDISETUJUI)->format('Y-m-d'),
        ];
    }
}
