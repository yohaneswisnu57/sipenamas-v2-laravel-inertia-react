<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FakultasResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'kode' => $this->KODEFAKULTAS,
            'nama' => $this->NAMAFAKULTAS,
        ];
    }
}
