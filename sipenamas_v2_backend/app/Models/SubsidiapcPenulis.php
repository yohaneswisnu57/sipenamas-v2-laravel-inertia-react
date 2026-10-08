<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubsidiapcPenulis extends Model
{
    protected $table = 'subsidiapc_penulis';

    public $timestamps = false;

    protected $guarded = [];

    public function subsidiApc()
    {
        return $this->belongsTo(SubsidiApc::class, 'IDPARENT', 'id');
    }

    public function person()
    {
        return $this->belongsTo(Person::class, 'KDPERSON', 'KODEPERSON');
    }
}
