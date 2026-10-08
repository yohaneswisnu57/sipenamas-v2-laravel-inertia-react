<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Cermin data dosen/pegawai (53 kolom, tabel legacy `person`) - BUKAN LAGI
 * model auth. Login/RBAC/token sekarang sepenuhnya di `App\Models\User`
 * (lihat User.php). Person dipertahankan apa adanya sebagai anchor 9 relasi
 * bisnis historis (Penelitian, PenelitianTim, PenelitianReviewer,
 * KegiatanAbdimas, KegiatanAbdimasTim, Insentif, SubsidiApc, HkiPeserta) -
 * KODEPERSON di kolom FK tabel-tabel itu adalah snapshot historis "siapa
 * yang melakukan X saat itu" dan TIDAK BOLEH di-remap mengikuti NIP
 * terbaru seseorang (NIP terbukti bisa berubah seumur karier - lihat
 * riset di docs/superpowers/specs jika ada, atau riwayat brainstorming).
 */
class Person extends Model
{
    protected $table = 'person';

    public $timestamps = false;

    protected $guarded = [];

    protected $hidden = [
        'PASWET',
        'PIN',
    ];

    protected function casts(): array
    {
        return [
            'ISSUPERUSER' => 'boolean',
            'ISDEKAN' => 'boolean',
            'ISREKTOR' => 'boolean',
            'ISPENELITI' => 'boolean',
            'ISREVIEWERPENELITIAN' => 'boolean',
            'ISREVIEWERABDIMAS' => 'boolean',
            'ISEXTERNAL' => 'boolean',
            'ISADMBAU' => 'boolean',
        ];
    }

    /**
     * Dipakai untuk route model binding ({person} di beberapa route non-
     * auth) - konsisten dengan histori KODEPERSON sebagai identitas publik,
     * bukan primary key auto-increment.
     */
    public function getRouteKeyName(): string
    {
        return 'KODEPERSON';
    }

    public function fakultas()
    {
        return $this->belongsTo(Fakultas::class, 'KDFAKULTAS', 'KODEFAKULTAS');
    }

    public function prodi()
    {
        return $this->belongsTo(Prodi::class, 'KDPRODI', 'KODEPRODI');
    }
}
