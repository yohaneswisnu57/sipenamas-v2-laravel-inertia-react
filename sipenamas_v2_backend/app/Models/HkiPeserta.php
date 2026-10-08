<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HkiPeserta extends Model
{
    protected $table = 'hki_peserta';

    public $timestamps = false;

    protected $guarded = [];

    public function hki()
    {
        return $this->belongsTo(Hki::class, 'IDPARENT', 'id');
    }

    public function person()
    {
        return $this->belongsTo(Person::class, 'NIP', 'KODEPERSON');
    }
}
