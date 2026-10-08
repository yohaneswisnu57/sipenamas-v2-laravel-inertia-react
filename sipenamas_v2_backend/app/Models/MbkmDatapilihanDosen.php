<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MbkmDatapilihanDosen extends Model
{
    protected $table = 'mbkm_datapilihan_dosen';

    public $timestamps = false;

    protected $guarded = [];

    public function pertanyaan()
    {
        return $this->belongsTo(MbkmDatapertanyaanDosen::class, 'IDPERTANYAAN', 'id');
    }
}
