<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SoalPenilaianProposal extends Model
{
    protected $table = 'soalpenilaianproposal';

    public $timestamps = false;

    protected $guarded = [];

    public function detail()
    {
        return $this->hasMany(SoalPenilaianProposalDetail::class, 'IDPARENT');
    }
}
