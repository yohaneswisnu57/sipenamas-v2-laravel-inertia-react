<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SoalPenilaianPresentasi extends Model
{
    protected $table = 'soalpenilaianpresentasi';

    public $timestamps = false;

    protected $guarded = [];

    public function detail()
    {
        return $this->hasMany(SoalPenilaianPresentasiDetail::class, 'IDPARENT');
    }
}
