<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Prodi extends Model
{
    protected $table = 'prodi';

    public $timestamps = false;

    protected $guarded = [];

    public function fakultas()
    {
        return $this->belongsTo(Fakultas::class, 'KDFAKULTAS', 'KODEFAKULTAS');
    }
}
