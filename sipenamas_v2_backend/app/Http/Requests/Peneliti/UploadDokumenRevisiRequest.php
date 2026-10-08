<?php

namespace App\Http\Requests\Peneliti;

use Illuminate\Foundation\Http\FormRequest;

class UploadDokumenRevisiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'dokumenRevisi' => ['required', 'file', 'mimes:pdf', 'max:10240'],
        ];
    }
}
