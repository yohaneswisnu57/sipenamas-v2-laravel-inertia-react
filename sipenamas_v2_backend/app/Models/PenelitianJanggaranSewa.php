<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PenelitianJanggaranSewa extends Model
{
    protected $table = 'penelitian_janggaran_4sewa';

    public $timestamps = false;

    protected $guarded = [];

    public function penelitian()
    {
        return $this->belongsTo(Penelitian::class, 'IDPARENT', 'id');
    }
}
