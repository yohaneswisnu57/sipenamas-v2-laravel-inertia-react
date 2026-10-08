<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JurnalPengarang extends Model
{
    protected $table = 'jurnal_pengarang';

    public $timestamps = false;

    protected $guarded = [];

    public function jurnal()
    {
        return $this->belongsTo(Jurnal::class, 'IDPARENT', 'id');
    }

    public function person()
    {
        return $this->belongsTo(Person::class, 'NIKNIDN', 'KODEPERSON');
    }
}
