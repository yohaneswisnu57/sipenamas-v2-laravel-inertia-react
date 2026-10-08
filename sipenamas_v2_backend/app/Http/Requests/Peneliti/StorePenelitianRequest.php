<?php

namespace App\Http\Requests\Peneliti;

use Illuminate\Foundation\Http\FormRequest;

class StorePenelitianRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($validator->errors()->hasAny(['komposisiBahanPeralatan', 'komposisiPerjalanan', 'komposisiLaporan'])) {
                return;
            }

            $total = (float) $this->input('komposisiBahanPeralatan')
                + (float) $this->input('komposisiPerjalanan')
                + (float) $this->input('komposisiLaporan')
                + 30;

            if (abs($total - 100) > 0.001) {
                $validator->errors()->add('komposisiBahanPeralatan', 'Pengisian komposisi dana harus berjumlah 100 persen (Honorarium 30%).');
            }
        });
    }

    public function rules(): array
    {
        return [
            'judul' => ['required', 'string', 'max:500'],
            'skimKode' => ['required', 'string', 'exists:skimpenelitian,KODESKIM'],
            'sumberDanaKode' => ['required', 'string', 'exists:sumberdana,KODESUMBERDANA'],
            'biayaUsulan' => ['required', 'numeric', 'min:0'],
            'bidangFokus' => ['nullable', 'string', 'max:100'],
            'tempatLokasi' => ['nullable', 'string', 'max:255'],
            'targetLuaran' => ['nullable', 'string'],
            'ringkasan' => ['nullable', 'string'],
            'ajukan' => ['sometimes', 'boolean'],

            'anggotaDosen' => ['array'],
            'anggotaDosen.*.npp' => ['required_with:anggotaDosen', 'string', 'distinct', 'exists:person,KODEPERSON'],
            'anggotaDosen.*.tugas' => ['nullable', 'string'],

            'anggotaMahasiswa' => ['array'],
            'anggotaMahasiswa.*.nim' => ['required_with:anggotaMahasiswa', 'string'],
            'anggotaMahasiswa.*.peran' => ['nullable', 'string'],

            // Mitra (anggota eksternal luar institusi) - tidak punya
            // KODEPERSON di sistem, jadi diisi manual (bukan lookup ke
            // tabel person seperti anggotaDosen).
            'mitra' => ['array'],
            'mitra.*.nama' => ['required_with:mitra', 'string', 'max:255'],
            'mitra.*.instansi' => ['required_with:mitra', 'string', 'max:255'],
            'mitra.*.tugas' => ['nullable', 'string'],

            // Komposisi dana (persen 0-100) - padanan permohonanpenelitian
            // doSimpan legacy: Bahan <= 70, Perjalanan <= 40, Laporan <= 5,
            // Honorarium tetap 30 (readOnly), total harus tepat 100.
            'komposisiBahanPeralatan' => ['required', 'numeric', 'min:0', 'max:70'],
            'komposisiPerjalanan' => ['required', 'numeric', 'min:0', 'max:40'],
            'komposisiLaporan' => ['required', 'numeric', 'min:0', 'max:5'],
        ];
    }
}
