<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MbkmDistribusivoucherMahasiswa extends Model
{
    protected $table = 'mbkm_distribusivoucher_mahasiswa';

    public $timestamps = false;

    protected $guarded = [];

    public function mahasiswa()
    {
        return $this->belongsTo(Mahasiswa::class, 'NIM', 'NIM');
    }
}
