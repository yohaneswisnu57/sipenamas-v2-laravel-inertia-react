<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Periode extends Model
{
    protected $table = 'periode';

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'ISAKTIF' => 'boolean',
        ];
    }

    public function gelombang()
    {
        return $this->hasMany(PeriodeGelombang::class, 'IDPARENT', 'id');
    }

    public function tglPelaksanaanSelesai(): ?string
    {
        $mulai = $this->TGLPELAKSANAANBEGIN;
        if (! $mulai || str_starts_with((string) $mulai, '0000')) {
            return null;
        }
        $selesai = $this->TGLPELAKSANAANEND;

        return $selesai && ! str_starts_with((string) $selesai, '0000')
            ? substr((string) $selesai, 0, 10)
            : substr((string) $mulai, 0, 10);
    }

    public function scopeAktif($query)
    {
        return $query->where('ISAKTIF', 1);
    }
}
