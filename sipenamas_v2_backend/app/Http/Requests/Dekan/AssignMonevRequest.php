<?php

namespace App\Http\Requests\Dekan;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignMonevRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'kodeperson' => ['nullable', 'string'],
            'jenis' => ['nullable', 'string', Rule::in(['PENELITIAN', 'ABDIMAS'])],
        ];
    }
}
