<?php

namespace App\Services\Proposal;

use App\Models\Mahasiswa;
use App\Models\Penelitian;
use App\Models\Periode;
use App\Models\Person;
use Illuminate\Support\Carbon;
use PhpOffice\PhpWord\TemplateProcessor;
use ZipArchive;

/**
 * Padanan pen/myphp/hasilpenelitian.php genFellembarpengesahan +
 * setfinalFellembarpengesahan: lembar pengesahan laporan akhir dari template
 * legacy TPL_HALAMAN_PENGESAHAN_LAPAKHIR_PENELITIAN.docx. Tiga gambar tanda
 * tangan (word/media/image1..3.jpeg) dikosongkan saat generate; saat final
 * gambar kedua (ketua peneliti) diganti checkmark; gambar pertama (Dekan)
 * diganti saat Dekan menyetujui (dkn/myphp/approvallaporan.php).
 */
class PengesahanLaporanAkhirDocxService
{
    private const TEMPLATE = 'templates/TPL_HALAMAN_PENGESAHAN_LAPAKHIR_PENELITIAN.docx';

    private const BULAN = [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

    public function __construct(private KetuaLppmResolver $ketuaLppm) {}

    /** @return string path ke file DOCX sementara */
    public function build(Penelitian $penelitian): string
    {
        $tpl = new TemplateProcessor(resource_path(self::TEMPLATE));

        $periode = Periode::where('ISAKTIF', 1)->first();
        $skim = $penelitian->skim;
        $dekanNik = $penelitian->prodi?->fakultas?->KDDEKAN;
        $lppm = $this->ketuaLppm->resolve();
        $ketuaNik = $penelitian->tim()->where('PERAN', 'KETUA')->value('NIKNIDN');
        $ketua = $ketuaNik ? Person::where('KODEPERSON', $ketuaNik)->first() : null;
        $luaran = $penelitian->rencanaTarget()->where('ISWAJIB', 1)->reorder()->orderByDesc('URUTAN')->first();

        foreach ([
            'NAMASKIM' => strtoupper((string) $skim?->NAMASKIM),
            'JUDULPENELITIAN' => $penelitian->JUDULPENELITIAN,
            'BIDANGPENELITIAN' => $penelitian->BIDANGPENELITIAN,
            'WAKTUPELAKSANAAN' => $periode ? $this->tanggalIndo($periode->TGLBEGIN).' - '.$this->tanggalIndo($periode->TGLEND) : '',
            'BIAYADARIUKWMS' => number_format($skim?->STATUSPEN === 'INTERNAL' ? (float) $penelitian->NOMINALDANA_FINAL : 0),
            'DANAMITRA' => number_format((float) $penelitian->LBRPENGESAHANLAPHASIL_DANAMITRA),
            'DANAINKIND' => number_format((float) $penelitian->LBRPENGESAHANLAPHASIL_DANAINKIND),
            'TTDKOTA' => 'Surabaya',
            'TGLBLNTHN' => $this->teksTanggal(now()),
            'NAMADEKAN' => $dekanNik ? Person::where('KODEPERSON', $dekanNik)->value('NAMALENGKAP') : '',
            'NIKDEKAN' => $dekanNik,
            'NIKKETUALPPM' => $lppm['nik'] ?? '',
            'NAMAKETUALPPM' => $lppm['nama'] ?? '',
            'NAMAKETUA' => $ketua?->NAMALENGKAP,
            'NIKNIDNKETUA' => $ketuaNik,
            'NIKKETUA' => $ketuaNik,
            'JABATANKETUA' => $ketua?->JABATAN,
            'PRODIKETUA' => $ketua?->prodi?->NAMAPRODI,
            'HPEMAILKETUA' => $this->hpEmail($ketua),
            'LUARAN' => trim($luaran?->KATEGORI.' '.$luaran?->SUBKATEGORI),
            'MGZ' => '',
        ] as $key => $value) {
            $tpl->setValue($key, htmlspecialchars((string) $value));
        }

        $anggota = $penelitian->tim()->where('PERAN', 'ANGGOTA')->with('person')->get();
        $tpl->cloneBlock('ANGGOTA', $anggota->count(), true, true);
        foreach ($anggota->values() as $i => $row) {
            $n = $i + 1;
            $tpl->setValue("R_NUANGGOTA#$n", $n);
            $tpl->setValue("R_NIKNIDNANGGOTA#$n", htmlspecialchars((string) $row->NIKNIDN));
            $tpl->setValue("R_NAMAANGGOTA#$n", htmlspecialchars((string) $row->person?->NAMALENGKAP));
        }

        $mahasiswa = $penelitian->mahasiswa()->get();
        $tpl->cloneBlock('MAHASISWA', $mahasiswa->count(), true, true);
        $tpl->cloneBlock('IFADAMAHASISWA', $mahasiswa->isEmpty() ? 0 : 1, true, true);
        foreach ($mahasiswa->values() as $i => $row) {
            $n = $i + 1;
            $nama = Mahasiswa::where('NIM', $row->NIM)->value('NAMAMAHASISWA');
            $tpl->setValue("R_NAMAMAHASISWA#$n", htmlspecialchars($nama.' ('.$row->NIM.')'));
        }

        $out = tempnam(sys_get_temp_dir(), 'lbrpengesahan_lapakhir_').'.docx';
        $tpl->saveAs($out);
        $this->stempel($out, ketuaSudahTtd: false);

        return $out;
    }

    /**
     * Ganti tiga gambar tanda tangan di dalam docx.
     */
    public function stempel(string $docxPath, bool $ketuaSudahTtd, bool $dekanSudahTtd = false): void
    {
        $kosong = resource_path('templates/kosong.jpg');
        $checkmark = resource_path('templates/checkmark.jpg');

        $zip = new ZipArchive;
        if ($zip->open($docxPath) !== true) {
            return;
        }

        $zip->addFile($dekanSudahTtd ? $checkmark : $kosong, 'word/media/image1.jpeg');
        $zip->addFile($ketuaSudahTtd ? $checkmark : $kosong, 'word/media/image2.jpeg');
        $zip->addFile($kosong, 'word/media/image3.jpeg');
        $zip->close();
    }

    private function tanggalIndo(mixed $tanggal): string
    {
        return Carbon::parse($tanggal)->format('j/n/Y');
    }

    private function teksTanggal(Carbon $tanggal): string
    {
        return $tanggal->day.' '.self::BULAN[$tanggal->month].' '.$tanggal->year;
    }

    private function hpEmail(?Person $ketua): string
    {
        $hp = (string) $ketua?->HP;
        $email = (string) $ketua?->EMAIL;

        if ($email === '') {
            return $hp;
        }

        return $hp !== '' ? "{$hp} ({$email})" : $email;
    }
}
