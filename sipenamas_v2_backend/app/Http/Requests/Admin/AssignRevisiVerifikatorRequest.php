<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class AssignRevisiVerifikatorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Harus ISREVIEWERPENELITIAN=1, bukan reviewer 1/2 proposal ini.
            // Validasi lebih dalam (bukan reviewer existing, belum final) ada di RevisiCycleService.
            'reviewerId' => ['required', 'string', 'exists:person,KODEPERSON'],
        ];
    }
}
