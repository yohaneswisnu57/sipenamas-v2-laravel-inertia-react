<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class FinalDecisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'string', 'in:LOLOS,TIDAK_LOLOS'],
            'biayaDisetujui' => ['required_if:status,LOLOS', 'nullable', 'numeric', 'min:0'],
        ];
    }
}
