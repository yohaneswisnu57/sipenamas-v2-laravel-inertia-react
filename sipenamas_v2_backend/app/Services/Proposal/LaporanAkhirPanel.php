<?php

namespace App\Services\Proposal;

use App\Models\Insentif;
use App\Models\Penelitian;
use App\Models\PenelitianRencanatarget;
use App\Models\Periode;
use App\Models\Soalkuesionerpeneliti;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

/**
 * Isi tiga jendela di halaman Laporan Akhir peneliti (legacy
 * hasilpenelitian.php): KUESIONER, KELENGKAPAN LAPORAN AKHIR, dan TARGET
 * CAPAIAN & LUARAN. Dipakai props halaman dan aksi yang mengubahnya.
 */
class LaporanAkhirPanel
{
    public const FOLDER_LEMBAR = 'proposal';

    public const FOLDER_LUARAN = 'penelitian';

    private const SKOR = ['A' => 1, 'B' => 2, 'C' => 3, 'D' => 4];

    public function __construct(private LaporanAkhirGate $gate) {}

    /**
     * Padanan kuesionerpenelitiandetail.php: null bila belum ada soal aktif.
     *
     * @return array<string, mixed>|null
     */
    public function kuesioner(User $user, Periode $periode): ?array
    {
        $pengisian = $this->gate->pengisianKuesioner($user, $periode);

        if (! $pengisian) {
            return null;
        }

        $soal = Soalkuesionerpeneliti::where('KODESOAL', $pengisian->KDSOAL)->first();

        $pertanyaan = $this->gate->jawabanQuery($pengisian)
            ->orderBy('soalkuesionerpeneliti_detail.NOMOR')
            ->get([
                'pengisiankuesionerpeneliti_detail.id',
                'pengisiankuesionerpeneliti_detail.NOMORSOAL',
                'pengisiankuesionerpeneliti_detail.JAWAB',
                'soalkuesionerpeneliti_detail.KELOMPOK',
                'soalkuesionerpeneliti_detail.URAIAN',
            ])
            ->map(fn ($row) => [
                'id' => $row->id,
                'nomor' => $row->NOMORSOAL,
                'kelompok' => $row->KELOMPOK,
                'uraian' => $row->URAIAN,
                'jawab' => $row->JAWAB ?: null,
            ]);

        return [
            'kdperiode' => $periode->KODEPERIODE,
            'isDone' => $this->gate->kuesionerSelesai($user, $periode),
            'kelompok' => [
                'A' => $soal?->KELOMPOK_A,
                'B' => $soal?->KELOMPOK_B,
                'C' => $soal?->KELOMPOK_C,
            ],
            'pilihan' => collect(self::SKOR)->map(fn (int $skor, string $kode) => [
                'kode' => $kode,
                'label' => $soal?->{"SKOR_{$skor}"},
            ])->values(),
            'pertanyaan' => $pertanyaan,
        ];
    }

    public function skorKuesioner(string $jawab): int
    {
        return self::SKOR[$jawab];
    }

    /**
     * @return array<string, mixed>
     */
    public function kelengkapan(Penelitian $penelitian): array
    {
        return [
            'id' => $penelitian->id,
            'judul' => $penelitian->JUDULPENELITIAN,
            'danaMitra' => (float) $penelitian->LBRPENGESAHANLAPHASIL_DANAMITRA,
            'danaInkind' => (float) $penelitian->LBRPENGESAHANLAPHASIL_DANAINKIND,
            'mahasiswa' => $penelitian->mahasiswa()->with('mahasiswa')->orderBy('NIM')->get()->map(fn ($row) => [
                'id' => $row->id,
                'nim' => $row->NIM,
                'nama' => $row->mahasiswa?->NAMAMAHASISWA,
            ]),
            'adaLembarPengesahan' => $this->lembarPath($penelitian) !== null,
            'isLembarPengesahanFinal' => (bool) $penelitian->LBRPENGESAHANLAPHASIL_ISFINAL,
            'isDisetujuiDekan' => (bool) $penelitian->ISDEKANAPPROVELAPORANAKHIR,
        ];
    }

    public function lembarPath(Penelitian $penelitian): ?string
    {
        $nama = $penelitian->LBRPENGESAHANLAPHASIL_NAMAFILE;
        $disk = Storage::disk('legacy_res');

        return $nama && $disk->exists(self::FOLDER_LEMBAR.'/'.$nama) ? $disk->path(self::FOLDER_LEMBAR.'/'.$nama) : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function capaian(Penelitian $penelitian): array
    {
        return [
            'id' => $penelitian->id,
            'judul' => $penelitian->JUDULPENELITIAN,
            'isInsentifFinal' => $this->insentifFinal($penelitian),
            'target' => $penelitian->rencanaTarget()->where('ISCHKTARGET', 1)->get()->map(fn (PenelitianRencanatarget $target) => [
                'id' => $target->id,
                'kategori' => $target->KATEGORI,
                'subkategori' => $target->SUBKATEGORI,
                'indikator' => $target->INDIKATORNYA,
                'isWajib' => (bool) $target->ISWAJIB,
                'isAdaInsentif' => (bool) $target->ISADAINSENTIF,
                'isRealisasi' => (bool) $target->ISCHKREALISASI,
                'keterangan' => $target->KETHASIL,
                'statusTayang' => $target->STATUSTAYANG ?: null,
                'adaDokumen' => filled($target->FILE_DOC),
                'ekstensi' => $target->FILE_EXT ?: null,
            ]),
        ];
    }

    /**
     * Padanan insentivejurnal2.php cekStatusfinal.
     */
    public function insentifFinal(Penelitian $penelitian): bool
    {
        return (bool) Insentif::where('IDPENELITIANREFF', $penelitian->id)->value('ISPENGAJUANFINAL');
    }
}
