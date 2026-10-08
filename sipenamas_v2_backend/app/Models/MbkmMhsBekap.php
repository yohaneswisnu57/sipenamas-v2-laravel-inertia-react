<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MbkmMhsBekap extends Model
{
    protected $table = 'mbkm_mhs_bekap';

    public $timestamps = false;

    protected $guarded = [];

    public function mahasiswa()
    {
        return $this->belongsTo(Mahasiswa::class, 'NIM', 'NIM');
    }
}
