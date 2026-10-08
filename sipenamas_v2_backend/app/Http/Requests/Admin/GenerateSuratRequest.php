<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class GenerateSuratRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'distinct', 'exists:penelitian,id'],
            'nomor' => ['required', 'integer', 'min:1'],
            'tanggal' => ['required', 'date'],
            'abaikanDuplikasi' => ['sometimes', 'boolean'],
        ];
    }
}
