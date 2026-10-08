<?php

namespace App\Models;

use App\Domain\Proposal\RevisiState;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Model utama usulan penelitian & abdimas - memetakan tabel legacy
 * `penelitian` (135 kolom, dipakai untuk JENIS_PA = 'PENELITIAN' maupun
 * 'ABDIMAS' internal skim lama; abdimas eksternal ada di tabel terpisah
 * `kegiatanabdimas`, lihat KegiatanAbdimas).
 */
class Penelitian extends Model
{
    protected $table = 'penelitian';

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'APPROVALPERMOHONAN_ISAPPROVEBYDEKAN' => 'boolean',
            'ISPENGAJUANFINAL' => 'boolean',
            'ISKESEDIAANFINAL' => 'boolean',
            'ISDOKUMENPROPOSALFINAL' => 'boolean',
            'ISBUTUHREVIEWERKETIGA' => 'boolean',
            'ISAPPROVEDBYLPPM' => 'boolean',
            'NOMINALDANA' => 'float',
            'NOMINALDANA_FINAL' => 'float',
        ];
    }

    public function ketua()
    {
        return $this->belongsTo(Person::class, 'PERMOHONANDIBUAT_KDPERSON', 'KODEPERSON');
    }

    public function periode()
    {
        return $this->belongsTo(Periode::class, 'KDPERIODE', 'KODEPERIODE');
    }

    public function skim()
    {
        return $this->belongsTo(SkimPenelitian::class, 'KDSKIMPENELITIAN', 'KODESKIM');
    }

    public function sumberDana()
    {
        return $this->belongsTo(SumberDana::class, 'KDSUMBERDANA', 'KODESUMBERDANA');
    }

    public function prodi()
    {
        return $this->belongsTo(Prodi::class, 'KDPRODI', 'KODEPRODI');
    }

    public function tim()
    {
        return $this->hasMany(PenelitianTim::class, 'IDPARENT', 'id');
    }

    public function mahasiswa()
    {
        return $this->hasMany(PenelitianMhs::class, 'IDPARENT', 'id');
    }

    public function rencanaTarget()
    {
        return $this->hasMany(PenelitianRencanatarget::class, 'IDPARENT', 'id')->orderBy('URUTAN');
    }

    public function reviewers()
    {
        return $this->hasMany(PenelitianReviewer::class, 'IDPARENT', 'id');
    }

    public function honorarium()
    {
        return $this->hasMany(PenelitianJanggaranHonorarium::class, 'IDPARENT', 'id');
    }

    public function pembelian()
    {
        return $this->hasMany(PenelitianJanggaranPembelian::class, 'IDPARENT', 'id');
    }

    public function perjalanan()
    {
        return $this->hasMany(PenelitianJanggaranPerjalanan::class, 'IDPARENT', 'id');
    }

    public function sewa()
    {
        return $this->hasMany(PenelitianJanggaranSewa::class, 'IDPARENT', 'id');
    }

    public function monevHasil()
    {
        return $this->hasMany(PenelitianMonevHasil::class, 'IDPARENT', 'id');
    }

    public function reviewerMonev()
    {
        return $this->belongsTo(Person::class, 'MONEVHASILBY', 'KODEPERSON');
    }

    public function mitra()
    {
        return $this->hasMany(V2PenelitianMitra::class, 'penelitian_id', 'id')->orderBy('urutan');
    }

    /**
     * Siklus revisi aktif dari kolom legacy - lihat App\Domain\Proposal\RevisiState.
     */
    public function activeRevisiCycle(): ?RevisiState
    {
        return RevisiState::of($this);
    }

    /**
     * Data scoping Peneliti (.agents/context.md): usulan sendiri (ketua) atau
     * sebagai anggota tim yang sudah dikonfirmasi masuk `penelitian_tim`.
     */
    public function scopeForPeneliti(Builder $query, array|string $kodePerson): Builder
    {
        // Terima array KODEPERSON (bukan cuma satu string) supaya riwayat
        // penelitian lama tetap kelihatan biarpun NIP orang ini sudah
        // berganti sejak proposal dibuat - lihat User::kodepersonAliases().
        $kodePerson = (array) $kodePerson;

        return $query->where(function (Builder $q) use ($kodePerson) {
            $q->whereIn('PERMOHONANDIBUAT_KDPERSON', $kodePerson)
                ->orWhereIn('id', function ($sub) use ($kodePerson) {
                    $sub->select('IDPARENT')
                        ->from('penelitian_tim')
                        ->whereIn('NIKNIDN', $kodePerson);
                });
        });
    }

    /**
     * Data scoping Dekan: hanya proposal dari prodi-prodi di bawah
     * fakultas dekan yang login (join prodi -> fakultas -> KDDEKAN).
     */
    public function scopeForDekan(Builder $query, array|string $kodeDekan): Builder
    {
        return $query->whereIn('KDPRODI', function ($sub) use ($kodeDekan) {
            $sub->select('prodi.KODEPRODI')
                ->from('prodi')
                ->join('fakultas', 'fakultas.KODEFAKULTAS', '=', 'prodi.KDFAKULTAS')
                ->whereIn('fakultas.KDDEKAN', (array) $kodeDekan);
        });
    }

    /**
     * Filter combo periode legacy (finalapproval.php, setreviewer.php):
     * kosong = periode aktif (glbKDPERIODE), `ALL`/`*` = semua periode,
     * selain itu tahun dari KODEPERIODE tsb.
     */
    public function scopeForKdperiode(Builder $query, ?string $kdperiode): Builder
    {
        if ($kdperiode === 'ALL' || $kdperiode === '*') {
            return $query;
        }

        $periode = filled($kdperiode)
            ? Periode::where('KODEPERIODE', $kdperiode)->firstOrFail()
            : Periode::aktif()->first();

        return $periode ? $query->where('PERIODEKEGIATAN_TAHUN', $periode->TAHUN) : $query;
    }

    /**
     * Data scoping Reviewer: hanya proposal yang ditugaskan lewat
     * `penelitian_reviewer.NIK`.
     */
    public function scopeForReviewer(Builder $query, array|string $nikReviewer): Builder
    {
        $nikReviewer = (array) $nikReviewer;

        return $query->whereIn('id', function ($sub) use ($nikReviewer) {
            $sub->select('IDPARENT')
                ->from('penelitian_reviewer')
                ->whereIn('NIK', $nikReviewer);
        });
    }
}
