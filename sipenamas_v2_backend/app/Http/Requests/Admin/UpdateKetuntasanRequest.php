<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateKetuntasanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(['-', 'TUNTAS', 'BELUM TUNTAS', 'TUNTAS BERSYARAT', 'BATAL'])],
        ];
    }
}
