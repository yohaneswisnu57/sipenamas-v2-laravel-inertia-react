<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MbkmDatapilihanTendik extends Model
{
    protected $table = 'mbkm_datapilihan_tendik';

    public $timestamps = false;

    protected $guarded = [];

    public function pertanyaan()
    {
        return $this->belongsTo(MbkmDatapertanyaanTendik::class, 'IDPERTANYAAN', 'id');
    }
}
