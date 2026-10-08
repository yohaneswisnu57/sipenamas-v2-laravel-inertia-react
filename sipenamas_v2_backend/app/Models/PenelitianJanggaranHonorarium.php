<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PenelitianJanggaranHonorarium extends Model
{
    protected $table = 'penelitian_janggaran_1honorarium';

    public $timestamps = false;

    protected $guarded = [];

    public function penelitian()
    {
        return $this->belongsTo(Penelitian::class, 'IDPARENT', 'id');
    }
}
