<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PenelitianJanggaranPerjalanan extends Model
{
    protected $table = 'penelitian_janggaran_3perjalanan';

    public $timestamps = false;

    protected $guarded = [];

    public function penelitian()
    {
        return $this->belongsTo(Penelitian::class, 'IDPARENT', 'id');
    }
}
