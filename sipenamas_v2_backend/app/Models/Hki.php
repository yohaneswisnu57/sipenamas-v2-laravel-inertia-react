<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Hki extends Model
{
    protected $table = 'hki';

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'VALID' => 'boolean',
        ];
    }

    public function peserta()
    {
        return $this->hasMany(HkiPeserta::class, 'IDPARENT', 'id');
    }

    /**
     * Kepemilikan HKI tidak lewat kolom langsung, tapi lewat tabel
     * penghubung `hki_peserta.NIP` (lihat .agents/schema.md klaster 8).
     */
    public function scopeForPeneliti(Builder $query, array|string $kodePerson): Builder
    {
        $kodePerson = (array) $kodePerson;

        return $query->whereIn('id', function ($sub) use ($kodePerson) {
            $sub->select('IDPARENT')
                ->from('hki_peserta')
                ->whereIn('NIP', $kodePerson);
        });
    }
}
