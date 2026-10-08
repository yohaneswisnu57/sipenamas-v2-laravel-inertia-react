<?php

namespace App\Http\Requests\Peneliti;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRencanaTargetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'targetIds' => ['present', 'array'],
            'targetIds.*' => ['integer'],
        ];
    }
}
