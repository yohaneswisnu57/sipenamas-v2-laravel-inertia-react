<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Tabel aditif baru - lihat migration create_v2_penelitian_mitra_table.
 */
class V2PenelitianMitra extends Model
{
    protected $table = 'v2_penelitian_mitra';

    protected $guarded = [];

    public function penelitian()
    {
        return $this->belongsTo(Penelitian::class, 'penelitian_id', 'id');
    }
}
