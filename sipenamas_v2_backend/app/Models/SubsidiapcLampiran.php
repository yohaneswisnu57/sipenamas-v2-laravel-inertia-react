<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubsidiapcLampiran extends Model
{
    protected $table = 'subsidiapc_lampiran';

    public $timestamps = false;

    protected $guarded = [];

    public function subsidiApc()
    {
        return $this->belongsTo(SubsidiApc::class, 'IDPARENT', 'id');
    }
}
