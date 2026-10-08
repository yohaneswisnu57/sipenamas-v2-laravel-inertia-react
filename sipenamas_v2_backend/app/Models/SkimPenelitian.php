<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SkimPenelitian extends Model
{
    protected $table = 'skimpenelitian';

    public $timestamps = false;

    protected $guarded = [];

    public function scopeAktif($query)
    {
        return $query->where('ISAKTIF', 1);
    }

    public function scopePenelitian($query)
    {
        return $query->where('ISABDIMAS', 0);
    }

    public function scopeAbdimas($query)
    {
        return $query->where('ISABDIMAS', 1);
    }
}
