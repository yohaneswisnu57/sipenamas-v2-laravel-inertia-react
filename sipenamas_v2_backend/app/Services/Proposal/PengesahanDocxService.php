<?php

namespace App\Services\Proposal;

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;
use PhpOffice\PhpWord\TemplateProcessor;

class PengesahanDocxService
{
    private const TEMPLATE = 'templates/TPL_HALAMAN_PENGESAHAN.docx';

    public function __construct(private ?PengesahanQrService $qr = null)
    {
        $this->qr ??= new PengesahanQrService;
    }

    /** @return string path ke file DOCX sementara */
    public function build(array $d): string
    {
        $tpl = new TemplateProcessor(dirname(__DIR__, 3).'/resources/'.self::TEMPLATE);
        $dekan = $d['dekan'] ?? null;
        $lppm = $d['lppm'] ?? null;

        foreach ([
            'NAMAPENELITIAN' => strtoupper($d['skim']),
            'JUDULPENELITIAN' => strtoupper($d['judul']),
            'BIDANGPENELITIAN' => strtoupper((string) $d['bidang']),
            'BIAYAKESELURUHAN' => $d['biaya'],
            'NAMAKETUA' => $d['ketua']['nama'],
            'NIKNIDNKETUA' => $d['ketua']['nik'],
            'NIKKETUA' => $d['ketua']['nik'],
            'JABATANKETUA' => $d['ketua']['jabatan'] ?? '',
            'PRODIKETUA' => $d['ketua']['prodi'] ?? '',
            'HPKETUA' => $d['ketua']['hp'] ?? '',
            'EMAILKETUA' => $d['ketua']['email'] ?? '',
            'NAMADEKAN' => $dekan['nama'] ?? '',
            'NIKDEKAN' => $dekan['nik'] ?? '',
            'NAMAKETUALPPM' => $lppm['nama'] ?? '',
            'NIKKETUALPPM' => $lppm['nik'] ?? '',
            'TTDKOTA' => $d['kota'] ?? 'Surabaya',
            'TGLBLNTHN' => $d['tanggal'] ?? date('d-m-Y'),
        ] as $key => $value) {
            $tpl->setValue($key, htmlspecialchars((string) $value));
        }

        $anggota = $d['anggota'] ?? [];
        if ($anggota === []) {
            $tpl->cloneBlock('ANGGOTA', 0);
        } else {
            $tpl->cloneBlock('ANGGOTA', count($anggota), true, true);
            foreach ($anggota as $i => $a) {
                $n = $i + 1;
                $tpl->setValue("R_NUANGGOTA#$n", $n);
                $tpl->setValue("R_NAMAANGGOTA#$n", htmlspecialchars((string) $a['nama']));
                $tpl->setValue("R_NIKNIDNANGGOTA#$n", $a['nik']);
            }
        }

        $tmp = [];
        $this->qrImage($tpl, 'IMGTTDDOSEN', ($d['ketuaTtd'] ?? true) ? $d['ketua'] : null, $tmp);
        $this->qrImage($tpl, 'IMGTTDDEKAN', $dekan, $tmp);
        $this->qrImage($tpl, 'IMGTTDLPPM', $lppm, $tmp);

        $out = tempnam(sys_get_temp_dir(), 'lbrpengesahan_').'.docx';
        $tpl->saveAs($out);
        array_map('unlink', $tmp);

        return $out;
    }

    private function qrImage(TemplateProcessor $tpl, string $key, ?array $signer, array &$tmp): void
    {
        if (! $signer) {
            $tpl->setValue($key, '');

            return;
        }
        $png = tempnam(sys_get_temp_dir(), 'qr_').'.png';
        file_put_contents($png, (new Builder(writer: new PngWriter, size: 200, margin: 5))
            ->build(data: $this->qr->payload($signer['nik'], $signer['nama']))->getString());
        $tmp[] = $png;
        $tpl->setImageValue($key, ['path' => $png, 'width' => 48, 'height' => 48, 'ratio' => true]);
    }
}
