<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ZModulModul extends Model
{
    protected $table = 'z_modulmodul';

    public $timestamps = false;

    protected $guarded = [];

    public function scopeAktif($query)
    {
        return $query->where('ISAKTIF', 1)->orderBy('URUTAN');
    }
}
