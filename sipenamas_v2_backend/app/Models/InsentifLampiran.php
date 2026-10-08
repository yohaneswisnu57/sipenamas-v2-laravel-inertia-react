<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InsentifLampiran extends Model
{
    protected $table = 'insentif_lampiran';

    public $timestamps = false;

    protected $guarded = [];

    public function insentif()
    {
        return $this->belongsTo(Insentif::class, 'IDPARENT', 'id');
    }
}
