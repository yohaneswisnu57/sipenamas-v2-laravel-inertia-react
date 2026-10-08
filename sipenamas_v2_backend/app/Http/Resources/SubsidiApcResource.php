<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Bentuk output mengikuti mockSubsidiApc. `invoiceFile`/`suratPenerimaanFile`
 * belum dipetakan - lampirannya ada di tabel terpisah `subsidiapc_lampiran`
 * yang belum masuk cakupan Fase 1 (upload dokumen).
 */
class SubsidiApcResource extends JsonResource
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
            'penerbit' => $this->PUBLISHER,
            'kategoriJurnal' => $this->INFOJURNAL_TERINDEKDALAM,
            'kategoriJurnalNama' => $this->indexJurnal?->NAMAINDEXJURNAL ?? $this->INFOJURNAL_TERINDEKDALAM,
            'nominalPengajuan' => (float) $this->NOMINALPENGAJUANAPC,
            'nominalDisetujui' => null,
            'status' => $this->RES_STATUSAPC ?: 'MENUNGGU_REVIEW_LPPM',
            'tglPengajuan' => optional($this->TANGGALPENGAJUAN)->format('Y-m-d'),
            'urlArtikel' => $this->URL,
            'invoiceFile' => null,
            'suratPenerimaanFile' => null,
        ];
    }
}
