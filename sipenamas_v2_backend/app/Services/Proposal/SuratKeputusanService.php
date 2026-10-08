<?php

namespace App\Services\Proposal;

use App\Models\Penelitian;
use App\Models\Periode;
use Carbon\Carbon;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\TemplateProcessor;
use RuntimeException;

/**
 * Surat Tugas (ST), Surat Tugas Penulisan Proposal (STPP) dan Surat
 * Pencairan Dana (SPD) - padanan adm/myphp/finalapproval.php legacy
 * (genfileSurattugas / genfileSurattugaspp / genfileSuratdana / setDokfinal).
 */
class SuratKeputusanService
{
    public const JENIS = ['ST', 'STPP', 'SPD'];

    private const FOLDER = 'proposal';

    private const BULAN = [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

    private const SATUAN = ['', 'satu', 'dua', 'tiga', 'empat', 'lima', 'enam', 'tujuh', 'delapan', 'sembilan', 'sepuluh', 'sebelas'];

    public function path(Penelitian $penelitian, string $jenis): ?string
    {
        $kolom = $jenis === 'SPD' ? 'CETAKSURATDANA_NAMAFILE' : 'CETAKSURATTUGAS_NAMAFILE';
        $relatif = self::FOLDER.'/'.$penelitian->{$kolom};

        return filled($penelitian->{$kolom}) && Storage::disk('legacy_res')->exists($relatif)
            ? Storage::disk('legacy_res')->path($relatif)
            : null;
    }

    /** Usulan LOLOS memakai 2 nomor (ST lalu SPD), selain itu 1 nomor (STPP). */
    public function jumlahNomor(Penelitian $penelitian): int
    {
        return $penelitian->STATUSFINALAPPROVAL === 'LOLOS' ? 2 : 1;
    }

    /** Legacy cekDuplikasinomor: ST, STPP, SPD berbagi ruang nomor. */
    public function nomorTerpakai(array $nomor, array $kecualiIds): array
    {
        return Penelitian::whereNotIn('id', $kecualiIds)
            ->where(fn ($q) => $q->whereIn('CETAKSURATTUGAS_NOMORSURAT', $nomor)->orWhereIn('CETAKSURATDANA_NOMORSURAT', $nomor))
            ->get(['CETAKSURATTUGAS_NOMORSURAT', 'CETAKSURATDANA_NOMORSURAT'])
            ->flatMap(fn ($p) => [$p->CETAKSURATTUGAS_NOMORSURAT, $p->CETAKSURATDANA_NOMORSURAT])
            ->map(fn ($n) => (int) $n)
            ->intersect($nomor)
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    /**
     * Legacy genfileSurat: LOLOS -> Surat Tugas (nomor N) + Surat Pencairan
     * Dana (N+1); selain itu Surat Tugas Penulisan Proposal (N).
     */
    public function generate(Penelitian $penelitian, int $nomor, string $tanggal): void
    {
        $penelitian->loadMissing(['prodi.fakultas', 'tim.person', 'ketua', 'skim']);
        $tanggalSurat = Carbon::parse($tanggal);
        $periodeAktif = Periode::where('ISAKTIF', 1)->first();
        $periodeKegiatan = Periode::where('TAHUN', $penelitian->PERIODEKEGIATAN_TAHUN)->first();
        $nominal = (int) round((float) $penelitian->NOMINALDANA_FINAL * 70 / 100);

        $umum = [
            'TGLSURAT' => $this->teksTanggal($tanggalSurat),
            'JUDULPENELITIAN' => htmlspecialchars((string) $penelitian->JUDULPENELITIAN),
            'NAMAFAKULTAS' => htmlspecialchars(ucwords(strtolower((string) $penelitian->prodi?->fakultas?->NAMAFAKULTAS))),
            'TANGGALAKHIRPENELITIAN' => $periodeAktif ? Carbon::parse($periodeAktif->TGLEND)->format('j-n-Y') : '',
        ];
        $tim = $penelitian->tim->sortBy('URUTAN')->values();
        $jumlahAnggota = $tim->where('PERAN', 'ANGGOTA')->count();

        if ($penelitian->STATUSFINALAPPROVAL !== 'LOLOS') {
            $tpl = $this->template('TPLSURATTUGASPENULISANPROPOSAL', $umum + ['NOMORSURAT' => $nomor, 'TANGGALAWALPENELITIAN' => '']);
            [$bebanKetua, $bebanAnggota] = $jumlahAnggota > 0 ? [2, round(2 / $jumlahAnggota, 2)] : [4, 0];
            $this->isiTim($tpl, $tim, fn ($a) => (string) $a->person?->NAMALENGKAP, fn ($a) => round(($a->PERAN === 'KETUA' ? $bebanKetua : $bebanAnggota) / 2, 2));
            $this->simpan($penelitian, $tpl, 'CETAKSURATTUGAS', "surattugaspp_{$penelitian->id}.docx", $nomor, $tanggalSurat);

            return;
        }

        $anggaran = [
            'TOTALBIAYA' => number_format($nominal, 0, ',', '.'),
            'TOTALTERBILANG' => $this->terbilang($nominal),
            'TAHUNSURAT' => now()->year,
            'KODEANGGARAN' => htmlspecialchars($this->kodeAnggaran($penelitian)),
        ];

        $tpl = $this->template('TPLSURATTUGAS', $umum + $anggaran + [
            'NOMORSURAT' => $nomor,
            'TXTTGLMULAI' => $periodeKegiatan?->TGLPELAKSANAANBEGIN ? Carbon::parse($periodeKegiatan->TGLPELAKSANAANBEGIN)->format('d-m-Y') : '',
            'TXTTGLSELESAI' => $periodeKegiatan?->TGLPELAKSANAANEND ? Carbon::parse($periodeKegiatan->TGLPELAKSANAANEND)->format('d-m-Y') : '',
        ]);
        $this->isiTim($tpl, $tim, fn ($a) => $a->person?->NAMALENGKAP." ({$a->NIKNIDN})", null);
        $this->simpan($penelitian, $tpl, 'CETAKSURATTUGAS', "surattugas_{$penelitian->id}.docx", $nomor, $tanggalSurat);

        $ketua = $tim->firstWhere('PERAN', 'KETUA')?->person?->NAMALENGKAP ?? $penelitian->ketua?->NAMALENGKAP;
        $tpl = $this->template('TPLSURATDANA', $umum + $anggaran + ['NOMORSURAT' => $nomor + 1, 'NAMAKETUAPENELITI' => htmlspecialchars((string) $ketua)]);
        $this->simpan($penelitian, $tpl, 'CETAKSURATDANA', "suratdana_{$penelitian->id}.docx", $nomor + 1, $tanggalSurat);
    }

    /** Jumlah dokumen yang belum FINAL (legacy cekBeforefinalisasinya). */
    public function jumlahBelumFinal(Penelitian $penelitian): int
    {
        return collect(['CETAKSURATTUGAS', 'CETAKSURATDANA'])
            ->filter(fn ($prefix) => filled($penelitian->{"{$prefix}_NAMAFILE"}) && $penelitian->{"{$prefix}_STATUS"} !== 'FINAL')
            ->count();
    }

    private function template(string $nama, array $nilai): TemplateProcessor
    {
        $tpl = new TemplateProcessor(resource_path("templates/{$nama}.docx"));
        foreach ($nilai as $kunci => $isi) {
            $tpl->setValue($kunci, $isi);
        }

        return $tpl;
    }

    private function isiTim(TemplateProcessor $tpl, $tim, callable $nama, ?callable $beban): void
    {
        $tpl->cloneRow('NOBAR', max($tim->count(), 1));
        foreach ($tim as $i => $anggota) {
            $n = $i + 1;
            $tpl->setValue("NOBAR#$n", $n);
            $tpl->setValue("NAMAPENELITI#$n", htmlspecialchars($nama($anggota)));
            if ($beban) {
                $tpl->setValue("BEBAN#$n", $beban($anggota));
            }
        }
    }

    private function simpan(Penelitian $penelitian, TemplateProcessor $tpl, string $prefix, string $namaFile, int $nomor, Carbon $tanggal): void
    {
        $tpl->saveAs($sementara = tempnam(sys_get_temp_dir(), 'surat_').'.docx');
        Storage::disk('legacy_res')->put(self::FOLDER.'/'.$namaFile, file_get_contents($sementara));
        @unlink($sementara);

        $penelitian->update([
            "{$prefix}_NAMAFILE" => $namaFile,
            "{$prefix}_NOMORSURAT" => $nomor,
            "{$prefix}_TANGGALSURAT" => $tanggal->toDateString(),
            "{$prefix}_QRCODE" => uniqid().$penelitian->id.uniqid(),
        ]);
    }

    private function kodeAnggaran(Penelitian $penelitian): string
    {
        $nomor = $penelitian->skim?->NOMORKODEANGGARAN;
        $keterangan = $nomor ? DB::table('tabelkodeanggaran')->where('NOMORKODE', $nomor)->value('KETERANGAN') : null;

        return $keterangan !== null ? "{$nomor} ({$keterangan})" : '';
    }

    /** Legacy setDokfinal: QR dibubuhkan ke ${MGZ} lalu status FINAL. Yang belum digenerate atau sudah FINAL dilewati. */
    public function setFinal(Penelitian $penelitian): void
    {
        foreach (['CETAKSURATTUGAS', 'CETAKSURATDANA'] as $prefix) {
            if (blank($penelitian->{"{$prefix}_NAMAFILE"}) || $penelitian->{"{$prefix}_STATUS"} === 'FINAL') {
                continue;
            }

            $jenis = $prefix === 'CETAKSURATDANA' ? 'SPD' : ($penelitian->STATUSFINALAPPROVAL === 'LOLOS' ? 'ST' : 'STPP');
            $relatif = self::FOLDER.'/'.$penelitian->{"{$prefix}_NAMAFILE"};
            if (! Storage::disk('legacy_res')->exists($relatif)) {
                throw new RuntimeException("File surat {$jenis} tidak ditemukan");
            }

            $png = tempnam(sys_get_temp_dir(), 'qr_').'.png';
            file_put_contents($png, (new Builder(writer: new PngWriter, size: 200, margin: 5))
                ->build(data: $this->urlVerifikasi($jenis, $penelitian->{"{$prefix}_QRCODE"}))->getString());

            $tpl = new TemplateProcessor(Storage::disk('legacy_res')->path($relatif));
            $tpl->setImageValue('MGZ', ['path' => $png, 'width' => 115, 'height' => 115, 'ratio' => true]);
            $tpl->saveAs($sementara = tempnam(sys_get_temp_dir(), 'surat_').'.docx');
            Storage::disk('legacy_res')->put($relatif, file_get_contents($sementara));
            @unlink($png);
            @unlink($sementara);

            $penelitian->update(["{$prefix}_STATUS" => 'FINAL']);
        }
    }

    /** Isi QR surat: halaman verifikasi publik V2 (padanan appz/dox/{JENIS}/{KODE} legacy). */
    public function urlVerifikasi(string $jenis, string $kode): string
    {
        return rtrim((string) config('services.dox.url'), '/')."/{$jenis}/{$kode}";
    }

    private function teksTanggal(Carbon $tanggal): string
    {
        return $tanggal->day.' '.self::BULAN[$tanggal->month].' '.$tanggal->year;
    }

    private function terbilang(int $angka): string
    {
        if ($angka < 12) {
            return self::SATUAN[$angka] ?: 'nol';
        }
        if ($angka < 20) {
            return self::SATUAN[$angka - 10].' belas';
        }
        if ($angka < 100) {
            return trim(self::SATUAN[intdiv($angka, 10)].' puluh '.self::SATUAN[$angka % 10]);
        }
        if ($angka < 200) {
            return trim('seratus '.($angka % 100 ? $this->terbilang($angka - 100) : ''));
        }
        if ($angka < 1000) {
            return trim(self::SATUAN[intdiv($angka, 100)].' ratus '.($angka % 100 ? $this->terbilang($angka % 100) : ''));
        }
        if ($angka < 2000) {
            return trim('seribu '.($angka % 1000 ? $this->terbilang($angka - 1000) : ''));
        }
        foreach ([1_000_000_000_000 => 'triliun', 1_000_000_000 => 'milyar', 1_000_000 => 'juta', 1000 => 'ribu'] as $nilai => $nama) {
            if ($angka >= $nilai) {
                return trim($this->terbilang(intdiv($angka, $nilai)).' '.$nama.' '.($angka % $nilai ? $this->terbilang($angka % $nilai) : ''));
            }
        }

        return '';
    }
}
