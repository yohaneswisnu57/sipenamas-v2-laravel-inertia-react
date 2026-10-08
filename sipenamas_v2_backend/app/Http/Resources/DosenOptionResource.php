<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Bentuk ringkas untuk autocomplete pemilihan anggota tim dosen
 * (permohonanpenelitiantim.php legacy) - bukan profil lengkap seperti
 * PersonResource.
 */
class DosenOptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'npp' => $this->KODEPERSON,
            'nama' => $this->NAMALENGKAP,
            'prodi' => $this->prodi?->NAMAPRODI,
        ];
    }
}
