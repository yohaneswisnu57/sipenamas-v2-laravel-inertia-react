<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Padanan editData() legacy adm/myphp/periode.php: KODEPERIODE tidak ikut
 * diubah (kode jadi kunci relasi ke penelitian & prodi_anggaran), status
 * aktif tetap lewat endpoint toggle-aktif.
 */
class UpdatePeriodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'tahun' => ['required', 'digits:4'],
            'nama' => ['required', 'string', 'max:200'],
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
