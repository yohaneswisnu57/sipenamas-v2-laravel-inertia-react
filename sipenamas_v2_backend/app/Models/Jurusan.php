<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Jurusan extends Model
{
    protected $table = 'jurusan';

    public $timestamps = false;

    protected $guarded = [];

    public function fakultas()
    {
        return $this->belongsTo(Fakultas::class, 'KDFAKULTAS', 'KODEFAKULTAS');
    }
}
