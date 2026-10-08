<?php

namespace App\Http\Requests\Reviewer;

use App\Enums\RevisiStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VerifikasiRevisiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Cuma dua keputusan yang valid dari endpoint ini - MENUNGGU_UPLOAD/
            // MENUNGGU_VERIFIKASI di RevisiStatus adalah state internal siklus,
            // bukan input yang boleh dikirim client.
            'status' => ['required', Rule::in([RevisiStatus::DISETUJUI->value, RevisiStatus::DITOLAK->value])],
            'catatan' => ['required', 'string', 'max:2000'],
        ];
    }
}
