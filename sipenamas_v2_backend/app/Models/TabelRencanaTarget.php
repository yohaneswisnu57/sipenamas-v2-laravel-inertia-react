<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TabelRencanaTarget extends Model
{
    protected $table = 'tabelrencanatarget';

    public $timestamps = false;

    protected $guarded = [];

    public function skim()
    {
        return $this->belongsTo(SkimPenelitian::class, 'KDSKIM', 'KODESKIM');
    }
}
