<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PenelitianMonevHasil extends Model
{
    protected $table = 'penelitian_monevhasil';

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'ISFINAL' => 'boolean',
        ];
    }

    public function penelitian()
    {
        return $this->belongsTo(Penelitian::class, 'IDPARENT', 'id');
    }
}
