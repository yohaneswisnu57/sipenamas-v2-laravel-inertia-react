<?php

namespace App\Http\Requests\Peneliti;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCapaianRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'realisasi' => ['required', 'boolean'],
            'keterangan' => ['nullable', 'string'],
            'statusTayang' => ['nullable', 'in:BELUM SUBMIT,SUBMITTED,ACCEPTED (LOA)'],
        ];
    }
}
