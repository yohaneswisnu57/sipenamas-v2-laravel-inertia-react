<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SkimResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'kode' => $this->KODESKIM,
            'nama' => $this->NAMASKIM,
            'maxDana' => (float) $this->ANGGARANPERPENELITIAN,
            'minAnggota' => (int) $this->MINANGGOTA,
            'maxAnggota' => (int) $this->MAXANGGOTA,
            'isAbdimas' => (bool) $this->ISABDIMAS,
        ];
    }
}
