<?php

namespace App\Http\Controllers\Api\V1\Dekan;

use App\Http\Controllers\Controller;
use App\Models\Penelitian;
use App\Models\Periode;
use App\Services\Proposal\DocxToPdfConverter;
use App\Services\Proposal\PengesahanLaporanAkhirDocxService;
use App\Support\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Padanan dkn/myphp/approvallaporan.php legacy: Dekan menyetujui laporan
 * akhir penelitian LOLOS di fakultasnya. Jendela PERSETUJUAN hanya terbuka
 * bila lembar pengesahan laporan akhir sudah final, dan persetujuan tidak
 * bisa dibatalkan dari UI.
 */
class LaporanAkhirController extends Controller
{
    public function index(Request $request)
    {
        $periode = $request->filled('kdperiode')
            ? Periode::where('KODEPERIODE', $request->query('kdperiode'))->firstOrFail()
            : Periode::where('ISAKTIF', 1)->firstOrFail();

        $items = $this->query($request)
            ->where('PERIODEKEGIATAN_TAHUN', $periode->TAHUN)
            ->with(['skim', 'ketua', 'rencanaTarget'])
            ->orderByDesc('LBRPENGESAHANLAPHASIL_ISFINAL')
            ->orderBy('ISDEKANAPPROVELAPORANAKHIR')
            ->get()
            ->map(function (Penelitian $penelitian) {
                $target = $penelitian->rencanaTarget->where('ISCHKTARGET', 1);

                return [
                    'id' => $penelitian->id,
                    'judul' => $penelitian->JUDULPENELITIAN,
                    'tahun' => $penelitian->PERIODEKEGIATAN_TAHUN,
                    'skim' => $penelitian->skim?->NAMASKIM,
                    'ketua' => $penelitian->ketua?->NAMALENGKAP,
                    'isLembarPengesahanFinal' => (bool) $penelitian->LBRPENGESAHANLAPHASIL_ISFINAL,
                    'isDisetujuiDekan' => (bool) $penelitian->ISDEKANAPPROVELAPORANAKHIR,
                    'jumlahTarget' => $target->count(),
                    'jumlahRealisasi' => $target->where('ISCHKREALISASI', 1)->count(),
                    'statusKetuntasan' => $penelitian->STATUSKETUNTASANPENELITIAN,
                ];
            });

        return ApiResponse::success(['kdperiode' => $periode->KODEPERIODE, 'items' => $items]);
    }

    /**
     * Padanan setApprovaldekan(true): tandai disetujui dan bubuhkan tanda
     * tangan Dekan di lembar pengesahan laporan akhir.
     */
    public function approve(Request $request, int $id, PengesahanLaporanAkhirDocxService $docx)
    {
        $penelitian = $this->siapDisetujui($request, $id);

        if (! $penelitian->ISDEKANAPPROVELAPORANAKHIR) {
            $penelitian->update([
                'ISDEKANAPPROVELAPORANAKHIR' => 1,
                'TSDEKANAPPROVELAPORANAKHIR' => $penelitian->TSDEKANAPPROVELAPORANAKHIR ?? now(),
            ]);

            if ($path = $this->lembarPath($penelitian)) {
                $docx->stempel($path, ketuaSudahTtd: true, dekanSudahTtd: true);
            }
        }

        return ApiResponse::success(null, 'Laporan akhir disetujui');
    }

    /**
     * Padanan cekFilelaporan: pratinjau lembar pengesahan laporan akhir.
     */
    public function lembarPengesahan(Request $request, int $id, DocxToPdfConverter $pdfConverter)
    {
        $path = $this->lembarPath($this->siapDisetujui($request, $id));

        abort_if(! $path, 404, 'Lembar pengesahan belum dibuat');

        try {
            return response()->file($pdfConverter->convert($path), ['Content-Type' => 'application/pdf'])->deleteFileAfterSend();
        } catch (RuntimeException $e) {
            return ApiResponse::error('Konversi PDF belum tersedia di server', 503);
        }
    }

    private function query(Request $request): Builder
    {
        return Penelitian::forDekan($request->user()->kodepersonAliases())
            ->where('ISPENGAJUANFINAL', 1)
            ->where('ISDOKUMENPROPOSALFINAL', 1)
            ->where('STATUSFINALAPPROVAL', 'LOLOS');
    }

    private function siapDisetujui(Request $request, int $id): Penelitian
    {
        $penelitian = $this->query($request)->findOrFail($id);

        abort_if(! $penelitian->LBRPENGESAHANLAPHASIL_ISFINAL, 422, 'Lembar pengesahan laporan akhir belum final');

        return $penelitian;
    }

    private function lembarPath(Penelitian $penelitian): ?string
    {
        $nama = $penelitian->LBRPENGESAHANLAPHASIL_NAMAFILE;
        $disk = Storage::disk('legacy_res');

        return $nama && $disk->exists('proposal/'.$nama) ? $disk->path('proposal/'.$nama) : null;
    }
}
