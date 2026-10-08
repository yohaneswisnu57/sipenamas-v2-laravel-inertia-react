<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PeriodeGelombang extends Model
{
    protected $table = 'periodegelombang';

    public $timestamps = false;

    protected $guarded = [];

    public function periode()
    {
        return $this->belongsTo(Periode::class, 'IDPARENT', 'id');
    }
}
