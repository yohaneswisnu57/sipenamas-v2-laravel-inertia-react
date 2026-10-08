<?php

namespace App\Http\Requests\Peneliti;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveMonevJawabanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nomor' => ['required', 'integer', 'between:1,8'],
            'jenis' => ['nullable', 'string', Rule::in(['PENELITIAN', 'ABDIMAS'])],
            'jawaban' => ['required', Rule::in(['A', 'B', 'C', 'D', 'E'])],
        ];
    }
}
