<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MbkmDatapilihanMahasiswa extends Model
{
    protected $table = 'mbkm_datapilihan_mahasiswa';

    public $timestamps = false;

    protected $guarded = [];

    public function pertanyaan()
    {
        return $this->belongsTo(MbkmDatapertanyaanMahasiswa::class, 'IDPERTANYAAN', 'id');
    }
}
