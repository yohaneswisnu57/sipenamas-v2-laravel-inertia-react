<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IndexJurnal extends Model
{
    protected $table = 'indexjurnal';

    public $timestamps = false;

    protected $guarded = [];

    public function jenisPublikasi()
    {
        return $this->belongsTo(Jenispublikasi::class, 'KDJENISPUBLIKASI', 'KODEJP');
    }
}
