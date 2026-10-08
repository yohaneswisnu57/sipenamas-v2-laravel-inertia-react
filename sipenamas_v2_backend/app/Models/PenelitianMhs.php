<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PenelitianMhs extends Model
{
    protected $table = 'penelitian_mhs';

    public $timestamps = false;

    protected $guarded = [];

    public function penelitian()
    {
        return $this->belongsTo(Penelitian::class, 'IDPARENT', 'id');
    }

    public function mahasiswa()
    {
        return $this->belongsTo(Mahasiswa::class, 'NIM', 'NIM');
    }
}
