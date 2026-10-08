<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KegiatanAbdimasTim extends Model
{
    protected $table = 'kegiatanabdimas_tim';

    public $timestamps = false;

    protected $guarded = [];

    public function kegiatan()
    {
        return $this->belongsTo(KegiatanAbdimas::class, 'IDPARENT', 'id');
    }

    public function person()
    {
        return $this->belongsTo(Person::class, 'NIKNIDN', 'KODEPERSON');
    }
}
