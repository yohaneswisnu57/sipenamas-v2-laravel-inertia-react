<?php

namespace App\Services\Proposal;

use App\Models\Person;
use App\Models\User;

class KetuaLppmResolver
{
    /** @return array{nik: string, nama: string}|null */
    public function resolve(): ?array
    {
        $nik = config('pengesahan.ketua_lppm_kodeperson');
        if (! $nik) {
            return null;
        }

        $nama = Person::where('KODEPERSON', $nik)->value('NAMALENGKAP')
            ?: User::where('kodeperson', $nik)->value('nama');

        return $nama ? ['nik' => (string) $nik, 'nama' => $nama] : null;
    }
}
