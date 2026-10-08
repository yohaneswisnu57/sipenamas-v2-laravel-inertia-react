<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MbkmDatapertanyaanDosen extends Model
{
    protected $table = 'mbkm_datapertanyaan_dosen';

    public $timestamps = false;

    protected $guarded = [];

    public function pilihan()
    {
        return $this->hasMany(MbkmDatapilihanDosen::class, 'IDPERTANYAAN', 'id');
    }
}
