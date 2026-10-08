<?php

namespace App\Http\Requests\Peneliti;

use Illuminate\Foundation\Http\FormRequest;

class StoreHkiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'judulCiptaan' => ['required', 'string', 'max:200'],
            'jenisCiptaan' => ['required', 'string', 'max:25'],
            'pesertaLain' => ['array'],
            'pesertaLain.*.nama' => ['required_with:pesertaLain', 'string', 'max:60'],
            'pesertaLain.*.jenisPeserta' => ['nullable', 'string', 'max:25'],
        ];
    }
}
