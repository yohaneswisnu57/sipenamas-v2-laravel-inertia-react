<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SoalPenilaianPoster extends Model
{
    protected $table = 'soalpenilaianposter';

    public $timestamps = false;

    protected $guarded = [];

    public function detail()
    {
        return $this->hasMany(SoalPenilaianPosterDetail::class, 'IDPARENT');
    }
}
