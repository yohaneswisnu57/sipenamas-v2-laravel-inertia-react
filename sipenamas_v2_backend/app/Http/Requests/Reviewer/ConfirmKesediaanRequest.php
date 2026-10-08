<?php

namespace App\Http\Requests\Reviewer;

use Illuminate\Foundation\Http\FormRequest;

class ConfirmKesediaanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'bersedia' => ['required', 'boolean'],
            // Legacy `penelitian_reviewer` cuma punya ISAPPROVED (0/1), tidak
            // ada kolom untuk menyimpan alasan penolakan - lihat catatan di
            // PenugasanController::confirmKesediaan(). Field ini diterima
            // tapi belum ada tempat penyimpanannya.
            'alasan' => ['nullable', 'string', 'max:500'],
        ];
    }
}
