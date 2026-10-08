<?php

namespace App\Http\Requests\Reviewer;

use App\Services\Proposal\BorangPenilaianService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SubmitPenilaianRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // DRAFT = simpan sementara (masih bisa diubah, isian boleh belum
            // lengkap), FINAL = kunci dan ikut direkap (legacy
            // penilaianproposal.php UPDATESTATUSNYA menyimpan STATUSPENILAIAN
            // apa adanya dari client). Default FINAL.
            'status' => ['sometimes', Rule::in(['DRAFT', 'FINAL'])],
            'skor' => ['required_unless:status,DRAFT', 'array'],
            'skor.*.nomor' => ['required', 'integer', 'distinct'],
            'skor.*.skor' => ['required', 'integer', Rule::in(BorangPenilaianService::PILIHAN_SKOR)],
            // FINAL mengikuti legacy rev/app.js onPenilaianproposal_btnSimpanClick:
            // rekomendasi biaya wajib diisi (bukan 0) dan komentar minimal 100 karakter.
            'catatan' => ['required_unless:status,DRAFT', 'nullable', 'string', 'max:2000', Rule::when($this->isFinal(), ['min:100'])],
            // TOLAK tidak dipilih reviewer - ditentukan otomatis dari total
            // skor (legacy penilaianproposal.php updateStatusnya).
            'rekomendasiStatus' => ['required_unless:status,DRAFT', 'nullable', Rule::in(['LOLOS', 'REVISI'])],
            'rekomendasiDana' => ['required_unless:status,DRAFT', 'nullable', 'numeric', $this->isFinal() ? 'gt:0' : 'min:0'],
            'komentarRevisi' => ['sometimes', 'array'],
            'komentarRevisi.*' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'catatan.min' => 'Komentar minimal berisi 100 karakter.',
            'rekomendasiDana.required_unless' => 'Mohon diisi rekomendasi biayanya.',
            'rekomendasiDana.gt' => 'Mohon diisi rekomendasi biayanya.',
        ];
    }

    private function isFinal(): bool
    {
        return $this->input('status', 'FINAL') !== 'DRAFT';
    }
}
