<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MbkmDatapertanyaanMahasiswa extends Model
{
    protected $table = 'mbkm_datapertanyaan_mahasiswa';

    public $timestamps = false;

    protected $guarded = [];

    public function pilihan()
    {
        return $this->hasMany(MbkmDatapilihanMahasiswa::class, 'IDPERTANYAAN', 'id');
    }
}
