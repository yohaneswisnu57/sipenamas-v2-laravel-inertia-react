<?php

namespace App\Http\Requests\Peneliti;

use Illuminate\Foundation\Http\FormRequest;

class StoreInsentifRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'judulArtikel' => ['required', 'string', 'max:250'],
            'namaJurnal' => ['required', 'string', 'max:100'],
            // Legacy combo "Terindeks Dalam" (store cmbindexjurnal): nilai KODEINDEXJURNAL.
            'tingkatJurnal' => ['required', 'string', 'exists:indexjurnal,KODEINDEXJURNAL'],
            'tahunTerbit' => ['required', 'digits:4'],
            'volume' => ['nullable', 'string', 'max:50'],
            'nomor' => ['nullable', 'string', 'max:50'],
            'urlArtikel' => ['nullable', 'url', 'max:250'],
            'doi' => ['nullable', 'string', 'max:200'],
            'idPenelitianReff' => ['nullable', 'integer', 'exists:penelitian,id'],
        ];
    }
}
