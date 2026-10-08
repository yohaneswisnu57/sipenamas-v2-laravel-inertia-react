<?php

namespace App\Enums;

/**
 * Mirror of ROLES di sipenamas_v2_frontend/src/utils/constants.js.
 * Urutan value = urutan prioritas modul aktif pertama, sama seperti loop
 * z_modulmodul di legacy dologin.php.
 */
enum Role: string
{
    case ADMIN = 'ADM';
    case PENELITI = 'PEN';
    case REVIEWER = 'REV';
    case DEKAN = 'DKN';
    case REKTORAT = 'RKT';
    case AKREDITASI = 'AKR';

    /**
     * Nama kolom flag hak akses modul pada tabel `person`.
     */
    public function groupAksesColumn(): string
    {
        return 'GROUPAKSES_'.$this->value;
    }
}
