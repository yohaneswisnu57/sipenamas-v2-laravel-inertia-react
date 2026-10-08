<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MbkmDatapertanyaanTendik extends Model
{
    protected $table = 'mbkm_datapertanyaan_tendik';

    public $timestamps = false;

    protected $guarded = [];

    public function pilihan()
    {
        return $this->hasMany(MbkmDatapilihanTendik::class, 'IDPERTANYAAN', 'id');
    }
}
