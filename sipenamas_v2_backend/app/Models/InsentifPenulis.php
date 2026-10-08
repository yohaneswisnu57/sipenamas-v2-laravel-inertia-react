<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InsentifPenulis extends Model
{
    protected $table = 'insentif_penulis';

    public $timestamps = false;

    protected $guarded = [];

    public function insentif()
    {
        return $this->belongsTo(Insentif::class, 'IDPARENT', 'id');
    }

    public function person()
    {
        return $this->belongsTo(Person::class, 'KDPERSON', 'KODEPERSON');
    }
}
