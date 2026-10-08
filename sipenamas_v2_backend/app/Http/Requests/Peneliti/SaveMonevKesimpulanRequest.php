<?php

namespace App\Http\Requests\Peneliti;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveMonevKesimpulanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'kesimpulan' => ['nullable', 'string'],
            'jenis' => ['nullable', 'string', Rule::in(['PENELITIAN', 'ABDIMAS'])],
            'isFinal' => ['required', 'boolean'],
        ];
    }
}
