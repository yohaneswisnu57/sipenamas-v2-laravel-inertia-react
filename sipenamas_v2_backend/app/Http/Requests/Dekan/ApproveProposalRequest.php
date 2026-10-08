<?php

namespace App\Http\Requests\Dekan;

use Illuminate\Foundation\Http\FormRequest;

class ApproveProposalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'catatan' => ['required', 'string', 'max:1000'],
        ];
    }
}
