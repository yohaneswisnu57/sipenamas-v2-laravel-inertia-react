<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Tabel legacy `insentif` tidak punya kolom nominal, jadi tidak ada field
 * nominal di sini. `tingkatJurnal` = KODEINDEXJURNAL (legacy combo
 * "Terindeks Dalam"), `tingkatJurnalNama` = NAMAINDEXJURNAL-nya.
 */
class InsentifResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'personId' => $this->KDPERSONPENGAJU,
            'namaDosen' => $this->pengaju?->NAMALENGKAP,
            'fakultas' => $this->pengaju?->fakultas?->NAMAFAKULTAS,
            'judulArtikel' => $this->JUDULARTIKEL,
            'namaJurnal' => $this->INFOJURNAL_NAMAJURNAL,
            'tingkatJurnal' => $this->INFOJURNAL_TERINDEKDALAM,
            'tingkatJurnalNama' => $this->indexJurnal?->NAMAINDEXJURNAL ?? $this->INFOJURNAL_TERINDEKDALAM,
            'tahunTerbit' => $this->TAHUN ? (string) $this->TAHUN : null,
            'volumeNomor' => $this->volumeNomor(),
            'status' => $this->RES_STATUSINSENTIF ?: 'VERIFIKASI_LPPM',
            'tglPengajuan' => optional($this->TANGGALPENGAJUAN)->format('Y-m-d'),
            'tglCair' => null,
        ];
    }

    /**
     * Format "Vol. X, No. Y" tapi jangan tinggalkan "Vol. , No." kalau
     * salah satu/keduanya kosong (VOLUME/NOMOR sering NULL di data legacy).
     */
    private function volumeNomor(): ?string
    {
        $parts = array_filter([
            $this->VOLUME ? "Vol. {$this->VOLUME}" : null,
            $this->NOMOR ? "No. {$this->NOMOR}" : null,
        ]);

        return $parts ? implode(', ', $parts) : null;
    }
}
