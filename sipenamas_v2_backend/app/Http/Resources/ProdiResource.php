<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProdiResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'kode' => $this->KODEPRODI,
            'nama' => $this->NAMAPRODI,
            'kodeFakultas' => $this->KDFAKULTAS,
        ];
    }
}
