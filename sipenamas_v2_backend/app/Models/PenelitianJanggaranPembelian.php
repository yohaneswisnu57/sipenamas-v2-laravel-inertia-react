<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PenelitianJanggaranPembelian extends Model
{
    protected $table = 'penelitian_janggaran_2pembelian';

    public $timestamps = false;

    protected $guarded = [];

    public function penelitian()
    {
        return $this->belongsTo(Penelitian::class, 'IDPARENT', 'id');
    }
}
