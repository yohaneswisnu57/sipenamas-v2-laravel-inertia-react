<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class TambahReviewerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reviewerBaruId' => ['required', 'string', 'exists:person,KODEPERSON'],
            'isPembanding' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Reviewer ke-3 tidak boleh sama dengan reviewer yang sudah aktif di
     * proposal ini, maupun ketua/anggota tim (konflik kepentingan) - sama
     * seperti AssignReviewerRequest.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $penelitian = $this->route('penelitian');

            if (! $penelitian) {
                return;
            }

            $reviewerAktif = $penelitian->reviewers()->pluck('NIK')->all();
            $timNiknidn = $penelitian->tim()->pluck('NIKNIDN')->all();

            $conflicted = array_unique(array_filter([
                $penelitian->PERMOHONANDIBUAT_KDPERSON,
                ...$timNiknidn,
                ...$reviewerAktif,
            ]));

            if (in_array($this->input('reviewerBaruId'), $conflicted, true)) {
                $validator->errors()->add(
                    'reviewerBaruId',
                    'Reviewer tidak boleh sama dengan reviewer yang sudah aktif, atau anggota tim/ketua penelitian (konflik kepentingan).'
                );
            }
        });
    }
}
