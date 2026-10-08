<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class AssignReviewerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reviewer1Id' => ['required', 'string', 'exists:person,KODEPERSON', 'different:reviewer2Id'],
            'reviewer2Id' => ['required', 'string', 'exists:person,KODEPERSON'],
        ];
    }

    /**
     * Reviewer tidak boleh menilai proposal miliknya sendiri - baik
     * sebagai ketua maupun anggota tim. Dicek di sini (bukan cuma
     * disembunyikan dari dropdown di frontend) supaya aturannya benar-benar
     * ditegakkan, bukan cuma soal tampilan.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $penelitian = $this->route('penelitian');

            if (! $penelitian) {
                return;
            }

            $timNiknidn = $penelitian->tim()->pluck('NIKNIDN')->all();
            $conflicted = array_unique(array_filter([
                $penelitian->PERMOHONANDIBUAT_KDPERSON,
                ...$timNiknidn,
            ]));

            foreach (['reviewer1Id', 'reviewer2Id'] as $field) {
                if (in_array($this->input($field), $conflicted, true)) {
                    $validator->errors()->add(
                        $field,
                        'Reviewer tidak boleh anggota tim atau ketua penelitian yang sama (konflik kepentingan).'
                    );
                }
            }
        });
    }
}
