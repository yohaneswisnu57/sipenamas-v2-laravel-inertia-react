<?php

namespace App\Http\Controllers\Web\Peneliti;

use App\Http\Controllers\Controller;
use App\Models\SkimPenelitian;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Unduhan panduan & template per skim, port legacy `cekFeltplproplap()`
 * (pen/myphp/opsi.php). Legacy menyimpan berkas di `res/tpldoc/` dengan
 * nama `FNAME_TPL<indek><PROPOSAL|LAPORAN|PANDUAN>`; indek 01-08 cocok
 * dengan KODESKIM INT01-INT08. Berkas dibaca dari disk `legacy_res`
 * (`tpldoc/`), sama seperti berkas legacy lain - tidak masuk git.
 */
class TemplateController extends Controller
{
    /**
     * @var array<string, array{kodeskim: ?string, skim: string, jenis: list<string>}>
     */
    private const SKIM = [
        'tpl01' => ['kodeskim' => 'INT01', 'skim' => 'Penelitian Reguler', 'jenis' => ['proposal', 'laporan', 'panduan']],
        'tpl02' => ['kodeskim' => 'INT02', 'skim' => 'Penelitian Dosen Unggul', 'jenis' => ['proposal', 'laporan', 'panduan']],
        'tpl03' => ['kodeskim' => 'INT03', 'skim' => 'Penelitian Dosen Pemula', 'jenis' => ['proposal', 'laporan', 'panduan']],
        'tpl04' => ['kodeskim' => 'INT04', 'skim' => 'Interdisciplinary Research Grant', 'jenis' => ['proposal', 'laporan', 'panduan']],
        'tpl05' => ['kodeskim' => 'INT05', 'skim' => 'PPOT Research Grant', 'jenis' => ['proposal', 'laporan', 'panduan']],
        'tpl06' => ['kodeskim' => 'INT06', 'skim' => 'PPPG Research Grant', 'jenis' => ['proposal', 'laporan', 'panduan']],
        'tpl07' => ['kodeskim' => 'INT07', 'skim' => 'ABDIMAS Grant', 'jenis' => ['proposal', 'laporan', 'panduan']],
        'tpl08' => ['kodeskim' => 'INT08', 'skim' => 'Abdimas Lintas Prodi', 'jenis' => ['proposal', 'laporan', 'panduan']],
        'tpl08b' => ['kodeskim' => null, 'skim' => 'LPJ Keuangan', 'jenis' => ['laporan']],
        'tpl09' => ['kodeskim' => null, 'skim' => 'Prosedur Operasi Standar Abdimas', 'jenis' => ['panduan']],
        'tpl10' => ['kodeskim' => null, 'skim' => 'Rencana Target', 'jenis' => ['panduan']],
    ];

    private const EXT = ['proposal' => 'docx', 'laporan' => 'docx', 'panduan' => 'pdf'];

    /**
     * Daftar template untuk dashboard peneliti.
     *
     * @return list<array<string, mixed>>
     */
    public static function katalog(): array
    {
        $namaSkim = SkimPenelitian::query()
            ->whereIn('KODESKIM', array_filter(array_column(self::SKIM, 'kodeskim')))
            ->pluck('NAMASKIM', 'KODESKIM');

        $data = [];
        foreach (self::SKIM as $indek => $info) {
            $skim = $namaSkim[$info['kodeskim']] ?? $info['skim'];
            foreach ($info['jenis'] as $jenis) {
                $data[] = [
                    'id' => "{$indek}-{$jenis}",
                    'kodeskim' => $info['kodeskim'],
                    'skim' => $skim,
                    'jenis' => $jenis,
                    'readOnly' => $jenis === 'panduan',
                    'name' => self::downloadName($skim, $jenis),
                    'description' => ucfirst($jenis).' '.$skim,
                ];
            }
        }

        return $data;
    }

    public function download(string $type): BinaryFileResponse
    {
        [$indek, $jenis] = array_pad(explode('-', $type, 2), 2, null);
        abort_unless(isset(self::SKIM[$indek]) && in_array($jenis, self::SKIM[$indek]['jenis'], true), 404, 'Template tidak ditemukan');

        $path = 'tpldoc/FNAME_'.strtoupper($indek).strtoupper($jenis).'.'.self::EXT[$jenis];
        abort_unless(Storage::disk('legacy_res')->exists($path), 404, 'Berkas template belum tersedia di server');

        $fullPath = Storage::disk('legacy_res')->path($path);

        // Legacy hanya menampilkan panduan di iframe (preview), tanpa unduh.
        if ($jenis === 'panduan') {
            return response()->file($fullPath, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="'.self::downloadName(self::SKIM[$indek]['skim'], $jenis).'"',
            ]);
        }

        return response()->download($fullPath, self::downloadName(self::SKIM[$indek]['skim'], $jenis));
    }

    private static function downloadName(string $skim, string $jenis): string
    {
        $label = ['proposal' => 'Template_Proposal', 'laporan' => 'Template_Laporan', 'panduan' => 'Panduan'][$jenis];

        return $label.'_'.preg_replace('/[^A-Za-z0-9]+/', '_', $skim).'.'.self::EXT[$jenis];
    }
}
