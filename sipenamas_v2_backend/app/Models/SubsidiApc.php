<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class SubsidiApc extends Model
{
    protected $table = 'subsidiapc';

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'APPROVALPERMOHONAN_ISAPPROVEBYDEKAN' => 'boolean',
            'ISPENGAJUANFINAL' => 'boolean',
            'NOMINALPENGAJUANAPC' => 'float',
        ];
    }

    public function pengaju()
    {
        return $this->belongsTo(Person::class, 'KDPERSONPENGAJU', 'KODEPERSON');
    }

    public function lampiran()
    {
        return $this->hasMany(SubsidiapcLampiran::class, 'IDPARENT', 'id');
    }

    public function penulis()
    {
        return $this->hasMany(SubsidiapcPenulis::class, 'IDPARENT', 'id');
    }

    public function jurusan()
    {
        return $this->belongsTo(Jurusan::class, 'KDJURUSAN', 'KODEJURUSAN');
    }

    public function prodi()
    {
        return $this->belongsTo(Prodi::class, 'KDPRODI', 'KODEPRODI');
    }

    public function indexJurnal()
    {
        return $this->belongsTo(IndexJurnal::class, 'INFOJURNAL_TERINDEKDALAM', 'KODEINDEXJURNAL');
    }

    public function jenisPublikasi()
    {
        return $this->belongsTo(Jenispublikasi::class, 'KDJENISPUBLIKASI', 'KODEJP');
    }

    public function dekan()
    {
        return $this->belongsTo(Person::class, 'APPROVALPERMOHONAN_KDDEKAN', 'KODEPERSON');
    }

    public function scopeForPeneliti(Builder $query, array|string $kodePerson): Builder
    {
        return $query->whereIn('KDPERSONPENGAJU', (array) $kodePerson);
    }
}
