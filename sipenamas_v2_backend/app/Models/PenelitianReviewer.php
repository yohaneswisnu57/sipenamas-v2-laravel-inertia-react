<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PenelitianReviewer extends Model
{
    protected $table = 'penelitian_reviewer';

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'ISAPPROVED' => 'boolean',
            'ISREVIEWERPEMBANDING' => 'boolean',
        ];
    }

    public function penelitian()
    {
        return $this->belongsTo(Penelitian::class, 'IDPARENT', 'id');
    }

    public function reviewer()
    {
        return $this->belongsTo(Person::class, 'NIK', 'KODEPERSON');
    }

    /**
     * Kesediaan dari kolom legacy: ISAPPROVED=1 atau sudah menilai = BERSEDIA;
     * ISAPPROVED=0 dengan TSAPPROVED terisi dan belum menilai = MENOLAK
     * (legacy tidak punya aksi tolak - TSAPPROVED jadi penanda sudah merespons).
     */
    public function statusKesediaan(): string
    {
        if ($this->ISAPPROVED || $this->STATUSPENILAIAN === 'FINAL') {
            return 'BERSEDIA';
        }

        return $this->TSAPPROVED ? 'MENOLAK' : 'MENUNGGU';
    }

    public function isMenolakTugas(): bool
    {
        return $this->statusKesediaan() === 'MENOLAK';
    }
}
