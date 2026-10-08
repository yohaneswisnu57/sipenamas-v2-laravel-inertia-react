<?php

namespace App\Enums;

use App\Models\SoalMonevAbdimas;
use App\Models\SoalMonevPenelitian;
use Illuminate\Database\Eloquent\Model;

/**
 * Nilai kolom legacy `penelitian.JENIS_PA`. Penelitian dan abdimas memakai
 * tabel yang sama; yang berbeda hanya filter jenis dan master soal monev.
 */
enum JenisPa: string
{
    case PENELITIAN = 'PENELITIAN';
    case ABDIMAS = 'ABDIMAS';

    /**
     * Jenis dari parameter request (`?jenis=`), default PENELITIAN.
     * Nilai di luar enum ditolak 422, bukan diam-diam dianggap PENELITIAN.
     */
    public static function fromRequest(?string $jenis): self
    {
        if (blank($jenis)) {
            return self::PENELITIAN;
        }

        return self::tryFrom(strtoupper($jenis))
            ?? abort(422, 'Jenis harus PENELITIAN atau ABDIMAS');
    }

    /**
     * Master soal monev per jenis: `soalmonevpenelitian` / `soalmonevabdimas`.
     * Jawabannya tetap disimpan di `penelitian_monevhasil` untuk kedua jenis.
     */
    public function soalMonev(): Model
    {
        return match ($this) {
            self::PENELITIAN => new SoalMonevPenelitian,
            self::ABDIMAS => new SoalMonevAbdimas,
        };
    }
}
