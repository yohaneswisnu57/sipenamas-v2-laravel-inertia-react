<?php

use App\Models\Fakultas;
use App\Models\FokusAbdimas;
use App\Models\FokusPenelitian;
use App\Models\IndexJurnal;
use App\Models\Jurusan;
use App\Models\Mahasiswa;
use App\Models\Person;
use App\Models\PersonSinta;
use App\Models\Prodi;
use App\Models\SkimPenelitian;
use App\Models\SoalMonevAbdimas;
use App\Models\SoalMonevPenelitian;
use App\Models\SoalPenilaianPoster;
use App\Models\SoalPenilaianPosterDetail;
use App\Models\SoalPenilaianPresentasi;
use App\Models\SoalPenilaianPresentasiDetail;
use App\Models\SoalPenilaianProposal;
use App\Models\SoalPenilaianProposalDetail;
use App\Models\SumberDana;
use App\Models\TabelKodeAnggaran;
use App\Models\TabelRencanaTarget;

/**
 * Registry untuk MasterDataCrudController - satu entri per submenu "Basis
 * Data" (lihat docs/superpowers/... plan). Field & mode (full CRUD vs
 * list-only) di-fact-check terhadap handler legacy appz/ONAIR/adm/myphp/*.php
 * (bukan cuma nama kolom mentah), supaya cakupan create/update/delete persis
 * sama seperti kapabilitas asli, tidak menebak dari skema DB semata.
 *
 * Setiap entri:
 *  - model     : FQCN Eloquent model (tabel legacy, $guarded = [])
 *  - label     : label untuk UI
 *  - orderBy   : kolom default sorting
 *  - readOnly  : true => hanya index() yang diizinkan (tidak ada kapabilitas
 *                create/update/delete di legacy - lihat 'mahasiswa' & 'person-sinta')
 *  - uniqueKey : kolom kode bisnis yang harus unik (opsional)
 *  - fillable  : ['KOLOM' => 'aturan validasi Laravel']
 *  - scope     : ['param' => nama query/body param, 'column' => kolom DB]
 *                dipakai saat index/store/update wajib difilter/diisi oleh
 *                satu nilai induk (mis. rencana-target per skim)
 *  - details   : ['model' => FQCN, 'parentKey' => 'IDPARENT', 'fillable' => [...]]
 *                untuk entity master-detail (Borang Penilaian)
 */
return [

    'fakultas' => [
        'model' => Fakultas::class,
        'label' => 'Fakultas',
        'orderBy' => 'KODEFAKULTAS',
        'uniqueKey' => 'KODEFAKULTAS',
        'fillable' => [
            'KODEFAKULTAS' => 'required|string|max:10',
            'NAMAFAKULTAS' => 'required|string|max:100',
            'KDDEKAN' => 'nullable|string|max:20',
        ],
    ],

    'jurusan' => [
        'model' => Jurusan::class,
        'label' => 'Jurusan',
        'orderBy' => 'KODEJURUSAN',
        'uniqueKey' => 'KODEJURUSAN',
        'fillable' => [
            'KODEJURUSAN' => 'required|string|max:10',
            'NAMAJURUSAN' => 'required|string|max:100',
            'KDFAKULTAS' => 'nullable|string|max:10',
        ],
    ],

    'prodi' => [
        'model' => Prodi::class,
        'label' => 'Program Studi',
        'orderBy' => 'KODEPRODI',
        'uniqueKey' => 'KODEPRODI',
        'fillable' => [
            'KODEPRODI' => 'required|string|max:10',
            'NAMAPRODI' => 'required|string|max:100',
            'KDJURUSAN' => 'nullable|string|max:10',
            'KDFAKULTAS' => 'nullable|string|max:10',
        ],
    ],

    'fokus-penelitian' => [
        'model' => FokusPenelitian::class,
        'label' => 'Fokus Penelitian',
        'orderBy' => 'URUTAN',
        'fillable' => [
            'DESKRIPSI' => 'required|string|max:200',
            'URUTAN' => 'nullable|integer',
        ],
    ],

    'fokus-abdimas' => [
        'model' => FokusAbdimas::class,
        'label' => 'Fokus ABDIMAS',
        'orderBy' => 'URUTAN',
        'fillable' => [
            'DESKRIPSI' => 'required|string|max:200',
            'URUTAN' => 'nullable|integer',
        ],
    ],

    'skim-penelitian' => [
        'model' => SkimPenelitian::class,
        'label' => 'Skim Penelitian',
        'orderBy' => 'URUTAN',
        'uniqueKey' => 'KODESKIM',
        'fillable' => [
            'KODESKIM' => 'required|string|max:10',
            'NAMASKIM' => 'required|string|max:150',
            'MINANGGOTA' => 'required|integer|min:0',
            'MAXANGGOTA' => 'required|integer|min:0',
            'ISABDIMAS' => 'required|boolean',
            'STATUSPEN' => 'nullable|string|max:12',
            'DEFKDSUMBERDANA' => 'nullable|string|max:10',
            'KETERANGAN' => 'nullable|string',
            'KDSOALPENILAIANPROPOSAL' => 'nullable|string|max:10',
            'KDSOALPENILAIANPOSTER' => 'nullable|string|max:10',
            'ISSTATUSPEMAPARAN' => 'required|boolean',
            'ISOPENBUDGET' => 'required|boolean',
            'ANGGARANPERPENELITIAN' => 'nullable|numeric|min:0',
            'ISAKTIF' => 'required|boolean',
            'NOMORKODEANGGARAN' => 'nullable|string|max:50',
        ],
    ],

    'rencana-target' => [
        'model' => TabelRencanaTarget::class,
        'label' => 'Rencana Target',
        'orderBy' => 'URUTAN',
        'scope' => ['param' => 'kdskim', 'column' => 'KDSKIM'],
        'fillable' => [
            'KATEGORI' => 'required|string|max:200',
            'SUBKATEGORI' => 'required|string|max:200',
            'ISWAJIB' => 'required|boolean',
            'ISADAINSENTIF' => 'required|boolean',
            'INDIKATORNYA' => 'nullable|string|max:50',
            'URUTAN' => 'nullable|integer',
        ],
    ],

    'sumber-dana' => [
        'model' => SumberDana::class,
        'label' => 'Sumber Dana',
        'orderBy' => 'KODESUMBERDANA',
        'uniqueKey' => 'KODESUMBERDANA',
        'fillable' => [
            'KODESUMBERDANA' => 'required|string|max:10',
            'NAMASUMBERDANA' => 'required|string|max:100',
            'ISDANALPPM' => 'required|boolean',
            'KETERANGAN' => 'nullable|string',
        ],
    ],

    'kode-anggaran' => [
        'model' => TabelKodeAnggaran::class,
        'label' => 'Tabel Kode Anggaran',
        'orderBy' => 'NOMORKODE',
        'fillable' => [
            'NOMORKODE' => 'required|string|max:50',
            'KETERANGAN' => 'nullable|string|max:100',
        ],
    ],

    'borang-proposal' => [
        'model' => SoalPenilaianProposal::class,
        'label' => 'Borang Penilaian Proposal',
        'orderBy' => 'id',
        'uniqueKey' => 'KODESOAL',
        'fillable' => [
            'KODESOAL' => 'required|string|max:10',
            'DESKRIPSI' => 'required|string|max:100',
        ],
        'details' => [
            'model' => SoalPenilaianProposalDetail::class,
            'parentKey' => 'IDPARENT',
            'orderBy' => 'NOMOR',
            'fillable' => [
                'NOMOR' => 'required|integer',
                'KRITERIAPENILAIAN' => 'required|string',
                'BOBOTPERSEN' => 'required|integer|min:0|max:100',
            ],
        ],
    ],

    'borang-poster' => [
        'model' => SoalPenilaianPoster::class,
        'label' => 'Borang Penilaian Poster',
        'orderBy' => 'id',
        'uniqueKey' => 'KODESOAL',
        'fillable' => [
            'KODESOAL' => 'required|string|max:10',
            'DESKRIPSI' => 'required|string|max:100',
        ],
        'details' => [
            'model' => SoalPenilaianPosterDetail::class,
            'parentKey' => 'IDPARENT',
            'orderBy' => 'NOMOR',
            'fillable' => [
                'NOMOR' => 'required|integer',
                'KRITERIAPENILAIAN' => 'required|string',
                'BOBOTPERSEN' => 'required|integer|min:0|max:100',
            ],
        ],
    ],

    'borang-presentasi' => [
        'model' => SoalPenilaianPresentasi::class,
        'label' => 'Borang Penilaian Presentasi',
        'orderBy' => 'id',
        'uniqueKey' => 'KODESOAL',
        'fillable' => [
            'KODESOAL' => 'required|string|max:10',
            'DESKRIPSI' => 'required|string|max:100',
        ],
        'details' => [
            'model' => SoalPenilaianPresentasiDetail::class,
            'parentKey' => 'IDPARENT',
            'orderBy' => 'NOMOR',
            'fillable' => [
                'NOMOR' => 'required|integer',
                'KRITERIAPENILAIAN' => 'required|string',
                'BOBOTPERSEN' => 'required|integer|min:0|max:100',
            ],
        ],
    ],

    'monev-penelitian' => [
        'model' => SoalMonevPenelitian::class,
        'label' => 'Borang Monev Penelitian',
        'orderBy' => 'NOMOR',
        'fillable' => [
            'NOMOR' => 'required|integer',
            'ASPEKPENILAIAN' => 'required|string',
            'PIL01' => 'nullable|string|max:200',
            'PIL02' => 'nullable|string|max:200',
            'PIL03' => 'nullable|string|max:200',
            'PIL04' => 'nullable|string|max:200',
            'PIL05' => 'nullable|string|max:200',
        ],
    ],

    'monev-abdimas' => [
        'model' => SoalMonevAbdimas::class,
        'label' => 'Borang Monev ABDIMAS',
        'orderBy' => 'NOMOR',
        'fillable' => [
            'NOMOR' => 'required|integer',
            'ASPEKPENILAIAN' => 'required|string',
            'PIL01' => 'nullable|string|max:200',
            'PIL02' => 'nullable|string|max:200',
            'PIL03' => 'nullable|string|max:200',
            'PIL04' => 'nullable|string|max:200',
            'PIL05' => 'nullable|string|max:200',
        ],
    ],

    'mahasiswa' => [
        'model' => Mahasiswa::class,
        'label' => 'Mahasiswa',
        'orderBy' => 'NAMAMAHASISWA',
        'readOnly' => true,
        'fillable' => [],
    ],

    'index-jurnal' => [
        'model' => IndexJurnal::class,
        'label' => 'Index Jurnal',
        'orderBy' => 'URUTAN',
        'uniqueKey' => 'KODEINDEXJURNAL',
        'fillable' => [
            'KODEINDEXJURNAL' => 'required|string|max:10',
            'NAMAINDEXJURNAL' => 'required|string|max:150',
            'ADAINSENTIF' => 'nullable|boolean',
            'BOLEHAPC' => 'nullable|boolean',
            'URUTAN' => 'nullable|integer',
        ],
    ],

    'personal' => [
        'model' => Person::class,
        'label' => 'Personal',
        'orderBy' => 'NAMALENGKAP',
        'uniqueKey' => 'KODEPERSON',
        'fillable' => [
            'KODEPERSON' => 'required|string|max:20',
            'NAMALENGKAP' => 'required|string|max:150',
            'GENDER' => 'nullable|string|max:1',
            'HP' => 'nullable|string|max:50',
            'EMAIL' => 'nullable|string|max:100',
            'KDFAKULTAS' => 'nullable|string|max:10',
            'KDPRODI' => 'nullable|string|max:10',
            'JABATAN' => 'nullable|string|max:100',
            'ISPENELITI' => 'nullable|boolean',
            'ISDEKAN' => 'nullable|boolean',
            'ISREKTOR' => 'nullable|boolean',
            'ISGJM' => 'nullable|boolean',
            'ISREVIEWERPENELITIAN' => 'nullable|boolean',
            'ISREVIEWERABDIMAS' => 'nullable|boolean',
            'ISEXTERNAL' => 'nullable|boolean',
            'ISOPERATORPENELITIAN' => 'nullable|boolean',
            'ISOPERATORABDIMAS' => 'nullable|boolean',
        ],
    ],

    'person-sinta' => [
        'model' => PersonSinta::class,
        'label' => 'Person-Sinta',
        'orderBy' => 'NAMA',
        'readOnly' => true,
        'fillable' => [],
    ],

];
