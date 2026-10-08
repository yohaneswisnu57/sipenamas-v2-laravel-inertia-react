<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Penelitian;
use App\Services\Proposal\DocxToPdfConverter;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Padanan appz/dox/index.php legacy: halaman publik (tanpa login) yang dibuka
 * dari QR surat. Kode QR dicocokkan ke kolom `*_QRCODE` sesuai jenis dokumen,
 * lalu isi dokumennya ditampilkan. Jenis `*X` (tabel penelitianexternal)
 * belum diport karena tabelnya tidak dipakai V2.
 */
class DokumenPublikController extends Controller
{
    /** @var array<string, string> jenis dokumen => prefix kolom di tabel penelitian */
    private const PREFIX = [
        'ST' => 'CETAKSURATTUGAS',
        'STPP' => 'CETAKSURATTUGAS',
        'SPD' => 'CETAKSURATDANA',
        'LPPP' => 'LBRPENGESAHANPROPOSAL',
        'LPPA' => 'LBRPENGESAHANPROPOSAL',
        'LPLAP' => 'LBRPENGESAHANLAPHASIL',
        'LPLAA' => 'LBRPENGESAHANLAPHASIL',
    ];

    public function __invoke(string $jenis, string $kode, DocxToPdfConverter $pdfConverter): Response|BinaryFileResponse
    {
        $relatif = $this->berkas(strtoupper($jenis), $kode);

        if ($relatif === null) {
            return $this->tidakDitemukan();
        }

        $sementara = tempnam(sys_get_temp_dir(), 'dox_').'.docx';
        copy(Storage::disk('legacy_res')->path($relatif), $sementara);

        try {
            $pdf = $pdfConverter->convert($sementara);
        } catch (RuntimeException) {
            return response('DOKUMEN GAGAL DITAMPILKAN, SILAKAN COBA LAGI.', 503)->header('Content-Type', 'text/plain; charset=UTF-8');
        } finally {
            @unlink($sementara);
        }

        return response()->file($pdf, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "inline; filename=\"{$jenis}.pdf\"",
        ])->deleteFileAfterSend();
    }

    private function berkas(string $jenis, string $kode): ?string
    {
        $prefix = self::PREFIX[$jenis] ?? null;

        if ($prefix === null || $kode === '') {
            return null;
        }

        $namaFile = Penelitian::where("{$prefix}_QRCODE", $kode)->value("{$prefix}_NAMAFILE");
        $relatif = 'proposal/'.$namaFile;

        return filled($namaFile) && Storage::disk('legacy_res')->exists($relatif) ? $relatif : null;
    }

    private function tidakDitemukan(): Response
    {
        return response('KODE SALAH ATAU DOKUMEN TIDAK DITEMUKAN.', 404)->header('Content-Type', 'text/plain; charset=UTF-8');
    }
}
