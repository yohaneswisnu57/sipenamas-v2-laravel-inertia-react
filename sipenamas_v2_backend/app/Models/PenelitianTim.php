<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PenelitianTim extends Model
{
    protected $table = 'penelitian_tim';

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'ISAPPROVED' => 'boolean',
        ];
    }

    public function penelitian()
    {
        return $this->belongsTo(Penelitian::class, 'IDPARENT', 'id');
    }

    public function person()
    {
        return $this->belongsTo(Person::class, 'NIKNIDN', 'KODEPERSON');
    }
}
