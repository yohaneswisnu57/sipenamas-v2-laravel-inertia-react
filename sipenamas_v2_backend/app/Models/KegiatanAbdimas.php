<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class KegiatanAbdimas extends Model
{
    protected $table = 'kegiatanabdimas';

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'APPROVALPERMOHONAN_ISAPPROVEBYDEKAN' => 'boolean',
            'ISPENGAJUANFINAL' => 'boolean',
            'ISDEKANMENYETUJUI' => 'boolean',
            'ISAPPROVEDBYLPPM' => 'boolean',
        ];
    }

    public function pengaju()
    {
        return $this->belongsTo(Person::class, 'KDPERSONPENGAJU', 'KODEPERSON');
    }

    public function tim()
    {
        return $this->hasMany(KegiatanAbdimasTim::class, 'IDPARENT', 'id');
    }

    public function scopeForPeneliti(Builder $query, array|string $kodePerson): Builder
    {
        $kodePerson = (array) $kodePerson;

        return $query->where(function (Builder $q) use ($kodePerson) {
            $q->whereIn('KDPERSONPENGAJU', $kodePerson)
                ->orWhereIn('id', function ($sub) use ($kodePerson) {
                    $sub->select('IDPARENT')
                        ->from('kegiatanabdimas_tim')
                        ->whereIn('NIKNIDN', $kodePerson);
                });
        });
    }
}
