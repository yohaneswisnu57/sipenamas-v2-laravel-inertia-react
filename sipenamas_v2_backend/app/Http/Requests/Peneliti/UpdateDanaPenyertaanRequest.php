<?php

namespace App\Http\Requests\Peneliti;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDanaPenyertaanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'danaMitra' => ['required', 'numeric', 'min:0'],
            'danaInkind' => ['required', 'numeric', 'min:0'],
        ];
    }
}
