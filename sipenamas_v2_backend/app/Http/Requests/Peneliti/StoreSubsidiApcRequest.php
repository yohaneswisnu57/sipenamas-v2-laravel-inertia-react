<?php

namespace App\Http\Requests\Peneliti;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSubsidiApcRequest extends FormRequest
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
            'penerbit' => ['nullable', 'string', 'max:100'],
            // Legacy combo "Terindeks Dalam" APC (store cmbindexjurnalapc): KODEINDEXJURNAL ber-BOLEHAPC.
            'kategoriJurnal' => ['required', 'string', Rule::exists('indexjurnal', 'KODEINDEXJURNAL')->where('BOLEHAPC', 1)],
            'nominalPengajuan' => ['required', 'numeric', 'min:0'],
            'urlArtikel' => ['nullable', 'url', 'max:250'],
            'doi' => ['nullable', 'string', 'max:200'],
        ];
    }
}
