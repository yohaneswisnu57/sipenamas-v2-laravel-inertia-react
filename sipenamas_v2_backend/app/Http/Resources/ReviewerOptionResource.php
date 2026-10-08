<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Bentuk ringkas untuk dropdown pemilihan reviewer di modul Admin
 * (padanan setreviewer.php legacy) - dipakai PlottingReviewerPage.jsx.
 */
class ReviewerOptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->KODEPERSON,
            'name' => $this->NAMALENGKAP,
            'prodi' => $this->prodi?->NAMAPRODI,
            'isExternal' => (bool) $this->ISEXTERNAL,
        ];
    }
}
