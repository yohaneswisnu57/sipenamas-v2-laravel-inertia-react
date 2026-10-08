<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StorePeriodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'kodeperiode' => ['required', 'string', 'max:10', 'unique:periode,KODEPERIODE'],
            'tahun' => ['required', 'digits:4'],
            'nama' => ['required', 'string', 'max:200'],
            'isaktif' => ['boolean'],
            'tglBukaUsulan' => ['required', 'date'],
            'tglTutupUsulan' => ['required', 'date', 'after_or_equal:tglBukaUsulan'],
            'tglBatasReview' => ['required', 'date', 'after_or_equal:tglTutupUsulan'],
            'tglBatasRevisi' => ['required', 'date', 'after_or_equal:tglBatasReview'],
            'tglMonev' => ['required', 'date', 'after_or_equal:tglBatasRevisi'],
            'tglLaporanAkhir' => ['required', 'date', 'after_or_equal:tglMonev'],
            'tglPelaksanaanMulai' => ['nullable', 'date'],
            'tglPelaksanaanSelesai' => ['nullable', 'date', 'after_or_equal:tglPelaksanaanMulai'],
        ];
    }
}
