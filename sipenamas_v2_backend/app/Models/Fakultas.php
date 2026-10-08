<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Fakultas extends Model
{
    protected $table = 'fakultas';

    public $timestamps = false;

    protected $guarded = [];

    public function prodi()
    {
        return $this->hasMany(Prodi::class, 'KDFAKULTAS', 'KODEFAKULTAS');
    }

    public function dekan()
    {
        return $this->belongsTo(Person::class, 'KDDEKAN', 'KODEPERSON');
    }
}
