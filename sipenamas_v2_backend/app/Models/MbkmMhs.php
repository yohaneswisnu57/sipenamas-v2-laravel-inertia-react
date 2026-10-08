<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MbkmMhs extends Model
{
    protected $table = 'mbkm_mhs';

    public $timestamps = false;

    protected $guarded = [];

    public function mahasiswa()
    {
        return $this->belongsTo(Mahasiswa::class, 'NIM', 'NIM');
    }
}
