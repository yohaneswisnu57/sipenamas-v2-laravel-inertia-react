<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Jurnal extends Model
{
    protected $table = 'jurnal';

    public $timestamps = false;

    protected $guarded = [];

    public function pengarang()
    {
        return $this->hasMany(JurnalPengarang::class, 'IDPARENT', 'id');
    }
}
